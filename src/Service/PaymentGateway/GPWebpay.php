<?php

namespace Greendot\EshopBundle\Service\PaymentGateway;

use Throwable;
use Alcohol\ISO4217;
use Psr\Log\LoggerInterface;
use Granam\GpWebPay\Settings;
use Granam\GpWebPay\DigestSigner;
use Granam\GpWebPay\CardPayRequest;
use Granam\GpWebPay\CardPayResponse;
use Doctrine\ORM\EntityManagerInterface;
use Granam\GpWebPay\Codes\CurrencyCodes;
use Granam\GpWebPay\CardPayRequestValues;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

//    For tests against testing payment gateway, you can use payment card.
//    Card number: 4056070000000008
//    Card validity: 12/2025
//    CVC2: 200
//    3D Secure password: password
#[WithMonologChannel('gateway.gpw')]
readonly class GPWebpay implements PaymentGatewayInterface
{
    public static function action(): PaymentTechnicalAction
    {
        return PaymentTechnicalAction::GLOBAL_PAYMENTS;
    }

    public function __construct(
        private string                 $private_key,
        private string                 $public_key,
        private string                 $private_pass,
        private string                 $merchant_id,
        private UrlGeneratorInterface  $urlGenerator,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {}

    /**
     * @throws Throwable
     */
    public function getPayLink(Purchase $purchase): string
    {
        $this->logger->info('GPW getPayLink initiated', [
            'purchaseId' => $purchase->getId(),
            'totalPrice' => $purchase->getTotalPrice(),
            'currency' => '203',
        ]);

        $payment = new Payment();
        $payment->setDate(new \DateTime());
        $payment->setPurchase($purchase);
        $payment->setExternalId(1);

        try {
            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            $this->logger->info('GPW payment entity persisted', [
                'paymentId' => $payment->getId(),
                'purchaseId' => $purchase->getId(),
            ]);
        } catch (Throwable $e) {
            $this->logger->error('GPW failed to persist payment', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        try {
            $settings = Settings::createForTest(
                $this->private_key,
                $this->private_pass,
                $this->public_key,
                $this->merchant_id,
                $this->urlGenerator->generate(
                    'shop_order_verify',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            );

            $currencyCodes = new CurrencyCodes(new ISO4217());
            $digestSigner = new DigestSigner($settings);

            $requestValues = CardPayRequestValues::createFromArray([
                'ORDERNUMBER' => $payment->getId(),
                'AMOUNT'      => $purchase->getTotalPrice(),
                'CURRENCY'    => '203',
                'DEPOSITFLAG' => true,
                'MERORDERNUM' => $purchase->getId(),
            ], $currencyCodes);

            $cardPayRequest = new CardPayRequest($requestValues, $settings, $digestSigner);

            return $cardPayRequest->getRequestUrlWithGetParameters();
        } catch (Throwable $e) {
            $this->logger->error('GPW failed to create payment request', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    public function verifyLink(): CardPayResponse
    {
        try {
            $settings = Settings::createForTest(
                $this->private_key,
                $this->private_pass,
                $this->public_key,
                $this->merchant_id,
                $this->urlGenerator->generate(
                    'shop_order_verify',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            );

            $currencyCodes = new CurrencyCodes(new ISO4217());
            $digestSigner = new DigestSigner($settings);

            return CardPayResponse::createFromArray($_GET, $settings, $digestSigner);
        } catch (Throwable $e) {
            $this->logger->error('GPW failed to verify payment link', [
                'error' => $e->getMessage(),
                'request' => $_GET,
            ]);
            throw $e;
        }
    }
}