<?php

namespace Greendot\EshopBundle\Tests\Service\PaymentGateway;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayClient;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayException;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayState;
use Greendot\EshopBundle\Service\PaymentGateway\UnresolvedPaymentException;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Greendot\EshopBundle\Enum\PaymentOutcome;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GoPayTest extends TestCase
{
    /** @var array<int, array{string, string, array}> */
    private array $httpRequests = [];

    public function testGetPayLinkCreatesGoPayPaymentAndPersistsItsId(): void
    {
        $purchase = $this->buildPurchase(150.5);
        $purchase->setClient((new Client())->setMail('john@example.com'));

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $actionLogger = new RecordingPaymentActionLogger();
        $goPay = $this->buildGateway(
            [
                new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800], JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    'id' => 3000000001,
                    'state' => 'CREATED',
                    'gw_url' => 'https://gw.sandbox.gopay.com/gw/v3/abc',
                ], JSON_THROW_ON_ERROR)),
            ],
            $entityManager,
            $actionLogger,
            new Request(),
        );

        $url = $goPay->getPayLink($purchase);

        $this->assertSame('https://gw.sandbox.gopay.com/gw/v3/abc', $url);

        $body = json_decode($this->httpRequests[1][2]['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(15050, $body['amount'], 'amount is sent in hundredths');
        $this->assertSame('CZK', $body['currency']);
        $this->assertSame('14048', $body['order_number']);
        $this->assertSame(['type' => 'ACCOUNT', 'goid' => 8925782195], $body['target']);
        $this->assertSame('https://example.com/shop_order_payment_return?gateway=gopay', $body['callback']['return_url']);
        $this->assertSame('https://example.com/shop_order_payment_notify?gateway=gopay', $body['callback']['notification_url']);
        $this->assertSame('john@example.com', $body['payer']['contact']['email']);
        $this->assertArrayNotHasKey('allowed_payment_instruments', $body['payer'], 'no restriction configured = every method of the GoID');
        $this->assertArrayNotHasKey('default_payment_instrument', $body['payer']);

        $this->assertCount(1, $persisted);
        $this->assertInstanceOf(Payment::class, $persisted[0]);
        $this->assertSame('3000000001', $persisted[0]->getExternalId());
        $this->assertSame(150.5, $persisted[0]->getAmount());

        $this->assertCount(1, $actionLogger->calls);
        [, $name, , , $data, $payment] = $actionLogger->calls[0];
        $this->assertSame('gopay_redirect', $name);
        $this->assertSame($url, $data['url']);
        $this->assertSame($persisted[0], $payment);
    }

    public function testGetPayLinkSendsConfiguredPaymentInstruments(): void
    {
        $goPay = $this->buildGateway(
            $this->createPaymentResponses(),
            $this->createMock(EntityManagerInterface::class),
            new RecordingPaymentActionLogger(),
            new Request(),
            ['PAYMENT_CARD', 'APPLE_PAY', 'GPAY'],
            'PAYMENT_CARD',
        );

        $goPay->getPayLink($this->buildPurchase(100.0));

        $body = json_decode($this->httpRequests[1][2]['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['PAYMENT_CARD', 'APPLE_PAY', 'GPAY'], $body['payer']['allowed_payment_instruments']);
        $this->assertSame('PAYMENT_CARD', $body['payer']['default_payment_instrument']);
        $this->assertArrayNotHasKey('contact', $body['payer'], 'purchase has no client e-mail');
    }

    #[DataProvider('languages')]
    public function testGetPayLinkUsesPurchaseLocale(?string $clientLocale, ?string $paymentTypeCurrencyLocale, string $expected): void
    {
        $purchase = $this->buildPurchase(100.0);
        if ($clientLocale !== null) {
            $purchase->setClient((new Client())->setLocale($clientLocale));
        }
        if ($paymentTypeCurrencyLocale !== null) {
            $purchase->setPaymentType((new PaymentType())->setCurrency((new Currency())->setDefaultLocale($paymentTypeCurrencyLocale)));
        }

        $goPay = $this->buildGateway(
            $this->createPaymentResponses(),
            $this->createMock(EntityManagerInterface::class),
            new RecordingPaymentActionLogger(),
            new Request(),
        );

        $goPay->getPayLink($purchase);

        $this->assertSame($expected, json_decode($this->httpRequests[1][2]['body'], true)['lang']);
    }

    /** Resolution order itself is covered by PurchaseLocaleResolverTest */
    public static function languages(): iterable
    {
        yield 'client locale' => ['sk', 'de', 'SK'];
        yield 'payment type currency locale' => [null, 'de_AT', 'DE'];
        yield 'app default locale' => [null, null, 'CS'];
        yield 'locale GoPay does not support' => ['ja', null, 'CS'];
    }

    public function testGetPayLinkThrowsWithoutOrderNumberAndCallsNothing(): void
    {
        $purchase = new Purchase();
        $purchase->setTotalMoney(new Money(150.0, 'CZK'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $goPay = $this->buildGateway([], $entityManager, new RecordingPaymentActionLogger(), new Request());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no order number');

        try {
            $goPay->getPayLink($purchase);
        } finally {
            $this->assertSame([], $this->httpRequests);
        }
    }

    public function testGetPayLinkRefusesZeroTotal(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $goPay = $this->buildGateway([], $entityManager, new RecordingPaymentActionLogger(), new Request());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not positive');

        try {
            $goPay->getPayLink($this->buildPurchase(0.0));
        } finally {
            $this->assertSame([], $this->httpRequests);
        }
    }

    public function testGetPayLinkDoesNotPersistPaymentWhenGoPayRejectsIt(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $goPay = $this->buildGateway(
            [
                new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800], JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode(['errors' => [['error_code' => 110]]], JSON_THROW_ON_ERROR), ['http_code' => 409]),
            ],
            $entityManager,
            new RecordingPaymentActionLogger(),
            new Request(),
        );

        $this->expectException(GoPayException::class);

        $goPay->getPayLink($this->buildPurchase(150.0));
    }

    public function testVerifyLinkFetchesStateOfPaymentFromQuery(): void
    {
        $goPay = $this->buildGateway(
            [
                new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800], JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode(['id' => 3000000001, 'state' => 'PAID', 'order_number' => '14048'], JSON_THROW_ON_ERROR)),
            ],
            $this->createMock(EntityManagerInterface::class),
            new RecordingPaymentActionLogger(),
            new Request(['id' => '3000000001']),
        );

        $status = $goPay->verifyLink();

        $this->assertSame(GoPayState::PAID, $status->state);
        $this->assertStringEndsWith('/payments/payment/3000000001', $this->httpRequests[1][1]);
    }

    public function testVerifyLinkRejectsInvalidId(): void
    {
        $goPay = $this->buildGateway(
            [],
            $this->createMock(EntityManagerInterface::class),
            new RecordingPaymentActionLogger(),
            new Request(['id' => '../oauth2/token']),
        );

        $this->expectException(BadRequestHttpException::class);

        $goPay->verifyLink();
    }

    #[DataProvider('goPayStates')]
    public function testResolveCallbackMapsGoPayStateToOutcome(string $state, PaymentOutcome $expected): void
    {
        $payment = $this->buildPayment();

        $result = $this->buildCallbackGateway($payment, $state, '14048')
            ->resolveCallback(new Request(['id' => '3000000001']));

        $this->assertSame($payment, $result->payment);
        $this->assertSame($expected, $result->outcome);
        $this->assertSame(15050, $result->amount);
        $this->assertSame('CZK', $result->currency);
    }

    public static function goPayStates(): iterable
    {
        yield 'paid' => ['PAID', PaymentOutcome::PAID];
        yield 'canceled' => ['CANCELED', PaymentOutcome::FAILED];
        yield 'timeouted' => ['TIMEOUTED', PaymentOutcome::FAILED];
        yield 'method chosen' => ['PAYMENT_METHOD_CHOSEN', PaymentOutcome::PENDING];
        yield 'refunded' => ['REFUNDED', PaymentOutcome::PENDING];
        yield 'state unknown to us' => ['SOME_NEW_STATE', PaymentOutcome::PENDING];
    }

    public function testResolveCallbackRejectsPaymentOfAnotherOrder(): void
    {
        // e.g. a GP webpay payment sharing the externalId, or a GoPay payment of a different order
        $goPay = $this->buildCallbackGateway($this->buildPayment(), 'PAID', '99999');

        $this->expectException(UnresolvedPaymentException::class);

        $goPay->resolveCallback(new Request(['id' => '3000000001']));
    }

    public function testResolveCallbackRejectsUnknownPayment(): void
    {
        $goPay = $this->buildCallbackGateway(null, 'PAID', '14048');

        $this->expectException(UnresolvedPaymentException::class);

        $goPay->resolveCallback(new Request(['id' => '3000000001']));
    }

    public function testResolveCallbackRejectsInvalidIdWithoutAnyRequest(): void
    {
        $goPay = $this->buildCallbackGateway($this->buildPayment(), 'PAID', '14048');

        try {
            $goPay->resolveCallback(new Request(['id' => '../oauth2/token']));
            $this->fail('UnresolvedPaymentException expected');
        } catch (UnresolvedPaymentException) {
            $this->assertSame([], $this->httpRequests);
        }
    }

    private function buildCallbackGateway(?Payment $payment, string $state, string $orderNumber): GoPay
    {
        $paymentRepository = $this->createMock(PaymentRepository::class);
        $paymentRepository->method('findOneBy')->with(['externalId' => '3000000001'])->willReturn($payment);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Payment::class)->willReturn($paymentRepository);

        return $this->buildGateway(
            [
                new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800], JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    'id' => 3000000001,
                    'state' => $state,
                    'order_number' => $orderNumber,
                    'amount' => 15050,
                    'currency' => 'CZK',
                ], JSON_THROW_ON_ERROR)),
            ],
            $entityManager,
            new RecordingPaymentActionLogger(),
            new Request(),
        );
    }

    private function buildPayment(): Payment
    {
        $payment = (new Payment())->setExternalId('3000000001')->setAmount(150.5);
        $this->buildPurchase(150.5)->addPayment($payment);

        return $payment;
    }

    private function buildGateway(
        array                       $responses,
        EntityManagerInterface      $entityManager,
        RecordingPaymentActionLogger $actionLogger,
        Request                     $request,
        array                       $allowedInstruments = [],
        ?string                     $defaultInstrument = null,
    ): GoPay
    {
        $this->httpRequests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses) {
            $this->httpRequests[] = [$method, $url, $options];

            return array_shift($responses) ?? throw new \LogicException('Unexpected HTTP request ' . $url);
        });

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            fn (string $route, array $parameters = []) => 'https://example.com/' . $route . ($parameters ? '?' . http_build_query($parameters) : '')
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $currencyManager = $this->createMock(CurrencyManager::class);
        $currencyManager->method('getForPurchase')->willReturn((new Currency())->setName('CZK'));

        return new GoPay(
            new GoPayClient($httpClient, 'cid', 'secret', '8925782195', 'https://gw.sandbox.gopay.com/api'),
            $urlGenerator,
            $entityManager,
            $this->createMock(LoggerInterface::class),
            new GoPayStubManagePurchase(),
            $currencyManager,
            $actionLogger,
            $requestStack,
            new PurchaseLocaleResolver('cs'),
            $allowedInstruments,
            $defaultInstrument,
        );
    }

    /** @return MockResponse[] token + created payment */
    private function createPaymentResponses(): array
    {
        return [
            new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['id' => 3000000001, 'state' => 'CREATED', 'gw_url' => 'https://gw.sandbox.gopay.com/gw/v3/abc'], JSON_THROW_ON_ERROR)),
        ];
    }

    private function buildPurchase(float $total): Purchase
    {
        $purchase = new Purchase();
        (new \ReflectionProperty($purchase, 'id'))->setValue($purchase, 14284);
        $purchase->assignOrderNumber(14048);
        $purchase->setTotalPrice($total);
        $purchase->setTotalMoney(new Money($total, 'CZK'));

        return $purchase;
    }
}

readonly class GoPayStubManagePurchase extends ManagePurchase
{
    public function __construct()
    {
    }

    public function preparePrices(Purchase $purchase): Purchase
    {
        return $purchase;
    }
}
