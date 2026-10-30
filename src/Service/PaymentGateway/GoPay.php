<?php

namespace Greendot\EshopBundle\Service\PaymentGateway;

use DateTime;
use RuntimeException;
use Throwable;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Enum\PaymentOutcome;
use Greendot\EshopBundle\Enum\PaymentActionType;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Greendot\EshopBundle\Service\Payment\PaymentActionLogger;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayClient;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayPaymentStatus;
use Greendot\EshopBundle\Money\Exception\PurchaseCurrencyMismatchException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

//    For tests against the sandbox (GOPAY_API_URL=https://gw.sandbox.gopay.com/api)
//    use the test payment cards listed in GoPay help (help.gopay.com -> test payment cards).
#[WithMonologChannel('gateway.gopay')]
readonly class GoPay implements PaymentCallbackGatewayInterface
{
    /* Languages supported by the GoPay gateway UI */
    private const LANGUAGES = ['CS', 'EN', 'SK', 'DE', 'RU', 'PL', 'HU', 'FR', 'RO', 'BG', 'HR', 'IT', 'ES', 'UK', 'ET', 'LT', 'LV', 'SL', 'PT'];
    private const DEFAULT_LANGUAGE = 'CS';

    public static function action(): PaymentTechnicalAction
    {
        return PaymentTechnicalAction::GOPAY;
    }

    public function __construct(
        private GoPayClient            $client,
        private UrlGeneratorInterface  $urlGenerator,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
        private ManagePurchase         $managePurchase,
        private CurrencyManager        $currencyManager,
        private PaymentActionLogger    $paymentActionLogger,
        private RequestStack           $requestStack,
    ) {}

    /**
     * @throws Throwable
     */
    public function getPayLink(Purchase $purchase): string
    {
        $paymentType = $purchase->getPaymentType();
        if ($paymentType?->getCurrency() !== null && $purchase->getCurrency() !== null
            && $paymentType->getCurrency() !== $purchase->getCurrency()
        ) {
            throw PurchaseCurrencyMismatchException::forPurchase($purchase);
        }

        // order_number is shown to the customer and in GoPay statements - it must be the order number, never the id.
        $orderNumber = $purchase->getOrderNumber();
        if ($orderNumber === null) {
            throw new RuntimeException(sprintf(
                'Purchase #%d has no order number; refusing to build a payment link.',
                $purchase->getId(),
            ));
        }

        $this->managePurchase->preparePrices($purchase);

        $currency = $this->currencyManager->getForPurchase($purchase);
        $money = $purchase->getTotalMoney();

        if ($money?->iso !== $currency->getIso()) {
            throw new RuntimeException(sprintf(
                'Purchase #%d total (%s) does not match its currency (%s); refusing to build a payment link.',
                $purchase->getId(),
                $money?->iso,
                $currency->getIso(),
            ));
        }

        if (!$money->isPositive()) {
            throw new RuntimeException(sprintf(
                'Purchase #%d total (%s) is not positive; refusing to build a payment link.',
                $purchase->getId(),
                $money,
            ));
        }

        // GoPay amounts are integers in the smallest currency unit (hundredths)
        $amount = (int)round($money->value * 100);

        $request = [
            'amount' => $amount,
            'currency' => $money->iso,
            'order_number' => (string)$orderNumber,
            'order_description' => sprintf('Objednávka %s', $orderNumber),
            'target' => [
                'type' => 'ACCOUNT',
                'goid' => $this->client->getGoId(),
            ],
            'callback' => [
                'return_url' => $this->urlGenerator->generate('shop_order_payment_return', ['gateway' => self::action()->value], UrlGeneratorInterface::ABSOLUTE_URL),
                'notification_url' => $this->urlGenerator->generate('shop_order_payment_notify', ['gateway' => self::action()->value], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'lang' => $this->resolveLanguage(),
        ];

        $email = $purchase->getClient()?->getMail();
        if ($email) {
            $request['payer'] = ['contact' => ['email' => $email]];
        }

        $this->logger->info('GoPay getPayLink initiated', [
            'purchaseId' => $purchase->getId(),
            'amount' => $amount,
            'currencyIso' => $money->iso,
        ]);

        try {
            $response = $this->client->createPayment($request);
        } catch (Throwable $e) {
            $this->logger->error('GoPay failed to create payment', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (empty($response['id']) || empty($response['gw_url'])) {
            throw new RuntimeException(sprintf(
                'GoPay create payment response for purchase #%d is missing id or gw_url.',
                $purchase->getId(),
            ));
        }

        // Payment is persisted only once GoPay accepted it, so its externalId always points to a real GoPay payment
        $payment = new Payment();
        $payment->setDate(new DateTime());
        $payment->setPurchase($purchase);
        $payment->setExternalId((string)$response['id']);
        $payment->setAmount($money->value);
        $payment->setCurrency($currency);

        try {
            $this->entityManager->persist($payment);
            $this->entityManager->flush();
        } catch (Throwable $e) {
            $this->logger->error('GoPay failed to persist payment', [
                'purchaseId' => $purchase->getId(),
                'goPayId' => $response['id'],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->paymentActionLogger->log(
            $purchase,
            PaymentActionType::GOPAY_REDIRECT->value,
            'client',
            null,
            [
                'url' => $response['gw_url'],
                'goPayId' => $response['id'],
                'state' => $response['state'] ?? null,
                ...$request,
            ],
            $payment,
        );
        $this->entityManager->flush();

        return $response['gw_url'];
    }

    /**
     * Reads the GoPay payment id GoPay appended to the return/notification URL
     * and asks the GoPay API for the actual payment state.
     *
     * @throws Throwable
     */
    public function verifyLink(): GoPayPaymentStatus
    {
        return $this->fetchStatus($this->requestStack->getCurrentRequest() ?? new Request());
    }

    /**
     * @throws Throwable
     */
    public function resolveCallback(Request $request): PaymentResult
    {
        try {
            $status = $this->fetchStatus($request);
        } catch (BadRequestHttpException $e) {
            throw new UnresolvedPaymentException($e->getMessage(), 0, $e);
        }

        $payment = $this->entityManager->getRepository(Payment::class)->findOneBy(['externalId' => $status->id]);
        $purchase = $payment?->getPurchase();
        if (!$purchase || $status->orderNumber === null || $status->orderNumber !== (string)$purchase->getOrderNumber()) {
            throw new UnresolvedPaymentException(sprintf(
                'GoPay payment %s (order number %s) does not match any of our payments (local payment #%s).',
                $status->id,
                $status->orderNumber ?? '-',
                $payment?->getId() ?? '-',
            ));
        }

        return new PaymentResult(
            $payment,
            match (true) {
                $status->state?->isPaid() === true => PaymentOutcome::PAID,
                $status->state?->isFailed() === true => PaymentOutcome::FAILED,
                default => PaymentOutcome::PENDING,
            },
            $status->amount,
            $status->currency,
            $status->raw,
        );
    }

    /**
     * @throws Throwable
     */
    private function fetchStatus(Request $request): GoPayPaymentStatus
    {
        $paymentId = (string)$request->query->get('id', '');
        if ($paymentId === '' || !ctype_digit($paymentId)) {
            throw new BadRequestHttpException('Missing or invalid GoPay payment id.');
        }

        try {
            $status = $this->client->getStatus($paymentId);
        } catch (Throwable $e) {
            $this->logger->error('GoPay failed to verify payment', [
                'goPayId' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($status->state === null) {
            $this->logger->warning('GoPay reported a payment state unknown to this integration', [
                'goPayId' => $paymentId,
                'state' => $status->rawState,
            ]);
        }

        $this->logger->info('GoPay payment status received', [
            'goPayId' => $paymentId,
            'state' => $status->rawState,
            'orderNumber' => $status->orderNumber,
        ]);

        return $status;
    }

    private function resolveLanguage(): string
    {
        $locale = strtoupper(substr((string)$this->requestStack->getCurrentRequest()?->getLocale(), 0, 2));

        return in_array($locale, self::LANGUAGES, true) ? $locale : self::DEFAULT_LANGUAGE;
    }
}
