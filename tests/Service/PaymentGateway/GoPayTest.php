<?php

namespace Greendot\EshopBundle\Tests\Service\PaymentGateway;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayClient;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayException;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayState;
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
                new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800])),
                new MockResponse(json_encode([
                    'id' => 3000000001,
                    'state' => 'CREATED',
                    'gw_url' => 'https://gw.sandbox.gopay.com/gw/v3/abc',
                ])),
            ],
            $entityManager,
            $actionLogger,
            new Request(),
        );

        $url = $goPay->getPayLink($purchase);

        $this->assertSame('https://gw.sandbox.gopay.com/gw/v3/abc', $url);

        $body = json_decode($this->httpRequests[1][2]['body'], true);
        $this->assertSame(15050, $body['amount'], 'amount is sent in hundredths');
        $this->assertSame('CZK', $body['currency']);
        $this->assertSame('14048', $body['order_number']);
        $this->assertSame(['type' => 'ACCOUNT', 'goid' => 8925782195], $body['target']);
        $this->assertSame('https://example.com/shop_order_verify_gopay', $body['callback']['return_url']);
        $this->assertSame('https://example.com/shop_order_notify_gopay', $body['callback']['notification_url']);
        $this->assertSame('john@example.com', $body['payer']['contact']['email']);

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
                new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800])),
                new MockResponse(json_encode(['errors' => [['error_code' => 110]]]), ['http_code' => 409]),
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
                new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800])),
                new MockResponse(json_encode(['id' => 3000000001, 'state' => 'PAID', 'order_number' => '14048'])),
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

    private function buildGateway(
        array                       $responses,
        EntityManagerInterface      $entityManager,
        RecordingPaymentActionLogger $actionLogger,
        Request                     $request,
    ): GoPay
    {
        $this->httpRequests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses) {
            $this->httpRequests[] = [$method, $url, $options];

            return array_shift($responses) ?? throw new \LogicException('Unexpected HTTP request ' . $url);
        });

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(fn (string $route) => 'https://example.com/' . $route);

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
        );
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
