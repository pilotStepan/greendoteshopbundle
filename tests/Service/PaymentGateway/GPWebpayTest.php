<?php

namespace Greendot\EshopBundle\Tests\Service\PaymentGateway;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Money\Exception\PurchaseCurrencyMismatchException;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Service\PaymentGateway\GPWebpay;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

class GPWebpayTest extends TestCase
{
    private static string $privateKeyFile;
    private static string $publicKeyFile;

    public static function setUpBeforeClass(): void
    {
        self::$privateKeyFile = __DIR__ . '/fixtures/test_private.pem';
        self::$publicKeyFile = __DIR__ . '/fixtures/test_public.pem';
    }

    public function testGetPayLinkLogsRedirectUrlAndSentParams(): void
    {
        $probeKey = openssl_pkey_get_private((string) file_get_contents(self::$privateKeyFile));
        if ($probeKey === false || !@openssl_sign('probe', $probeSignature, $probeKey, OPENSSL_ALGO_SHA1)) {
            self::markTestSkipped('This OpenSSL build cannot produce RSA-SHA1 signatures required by GP Webpay.');
        }

        $purchase = new Purchase();
        (new \ReflectionProperty($purchase, 'id'))->setValue($purchase, 14284);
        $purchase->assignOrderNumber(14048);
        $purchase->setTotalPrice(150.0);
        $purchase->setTotalMoney(new Money(150.0, 'CZK'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function ($payment) {
            $idProperty = new \ReflectionProperty($payment, 'id');
            $idProperty->setValue($payment, 999);
        });
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/order/verify');

        $paymentActionLogger = new RecordingPaymentActionLogger();

        $gpWebpay = new GPWebpay(
            self::$privateKeyFile,
            self::$publicKeyFile,
            '',
            '123456',
            $urlGenerator,
            $entityManager,
            $this->createMock(LoggerInterface::class),
            new StubManagePurchase(),
            $this->buildCurrencyManager('CZK'),
            $paymentActionLogger,
            'test',
        );

        $redirectUrl = $gpWebpay->getPayLink($purchase);

        $this->assertCount(1, $paymentActionLogger->calls);
        [$loggedPurchase, $name, $performedBy, $description, $data, $payment] = $paymentActionLogger->calls[0];

        $this->assertSame($purchase, $loggedPurchase);
        $this->assertSame('gpw_redirect', $name);
        $this->assertSame('client', $performedBy);
        $this->assertSame($redirectUrl, $data['url']);
        $this->assertSame(150.0, $data['AMOUNT']);
        $this->assertSame('203', $data['CURRENCY'], 'CZK ISO 4217 numeric code is 203');
        $this->assertSame('CZK', $data['CURRENCY_ISO']);
        $this->assertSame(999, $data['ORDERNUMBER']);
        $this->assertSame(14048, $data['MERORDERNUM'], 'MERORDERNUM is the VS in GP webpay statements - must be the order number, not the purchase id');
        $this->assertStringContainsString('MERORDERNUM=14048', $redirectUrl);
        $this->assertNotNull($payment);

        // The gateway attempt itself must carry what it was actually asked to charge.
        assert($payment instanceof Payment);
        $this->assertSame(150.0, $payment->getAmount());
        $this->assertSame('CZK', $payment->getCurrency()?->getIso());
    }

    public function testGetPayLinkForEurPurchaseSendsEurCurrencyCode(): void
    {
        $probeKey = openssl_pkey_get_private((string) file_get_contents(self::$privateKeyFile));
        if ($probeKey === false || !@openssl_sign('probe', $probeSignature, $probeKey, OPENSSL_ALGO_SHA1)) {
            self::markTestSkipped('This OpenSSL build cannot produce RSA-SHA1 signatures required by GP Webpay.');
        }

        $purchase = new Purchase();
        $purchase->assignOrderNumber(1);
        $purchase->setTotalPrice(99.5);
        $purchase->setTotalMoney(new Money(99.5, 'EUR'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function ($payment) {
            $idProperty = new \ReflectionProperty($payment, 'id');
            $idProperty->setValue($payment, 1000);
        });
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/order/verify');

        $paymentActionLogger = new RecordingPaymentActionLogger();

        $gpWebpay = new GPWebpay(
            self::$privateKeyFile,
            self::$publicKeyFile,
            '',
            '123456',
            $urlGenerator,
            $entityManager,
            $this->createMock(LoggerInterface::class),
            new StubManagePurchase(),
            $this->buildCurrencyManager('EUR'),
            $paymentActionLogger,
            'test',
        );

        $gpWebpay->getPayLink($purchase);

        [, , , , $data, $payment] = $paymentActionLogger->calls[0];

        $this->assertSame('978', $data['CURRENCY'], 'EUR ISO 4217 numeric code is 978');
        $this->assertSame('EUR', $data['CURRENCY_ISO']);
        assert($payment instanceof Payment);
        $this->assertSame('EUR', $payment->getCurrency()?->getIso());
    }

    public function testGetPayLinkThrowsWhenPaymentTypeCurrencyDiffersFromPurchase(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2);

        $purchase = new Purchase();
        $purchase->setPaymentType((new PaymentType())->setCurrency($eur));
        $purchase->setCurrency($czk); // simulates a drifted/legacy currency snapshot
        $purchase->setTotalMoney(new Money(150.0, 'CZK'));

        $gpWebpay = new GPWebpay(
            self::$privateKeyFile,
            self::$publicKeyFile,
            '',
            '123456',
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
            new StubManagePurchase(),
            $this->buildCurrencyManager('CZK'),
            new RecordingPaymentActionLogger(),
            'test',
        );

        $this->expectException(PurchaseCurrencyMismatchException::class);

        $gpWebpay->getPayLink($purchase);
    }

    public function testGetPayLinkThrowsWithoutOrderNumberAndPersistsNoPayment(): void
    {
        $purchase = new Purchase();
        $purchase->setTotalMoney(new Money(150.0, 'CZK'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $gpWebpay = new GPWebpay(
            self::$privateKeyFile,
            self::$publicKeyFile,
            '',
            '123456',
            $this->createMock(UrlGeneratorInterface::class),
            $entityManager,
            $this->createMock(LoggerInterface::class),
            new StubManagePurchase(),
            $this->buildCurrencyManager('CZK'),
            new RecordingPaymentActionLogger(),
            'test',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no order number');

        $gpWebpay->getPayLink($purchase);
    }

    /**
     * GP webpay rejects AMOUNT=0 with PRCODE 3 / SRCODE 6 ("Incorrect content of field, AMOUNT"),
     * e.g. for an order fully covered by a gift voucher - no Payment row and no redirect may be created.
     */
    #[DataProvider('nonPositiveTotalProvider')]
    public function testGetPayLinkThrowsForNonPositiveTotalAndPersistsNoPayment(float $total): void
    {
        $purchase = new Purchase();
        $purchase->assignOrderNumber(14270);
        $purchase->setTotalMoney(new Money($total, 'CZK'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $paymentActionLogger = new RecordingPaymentActionLogger();

        $gpWebpay = new GPWebpay(
            self::$privateKeyFile,
            self::$publicKeyFile,
            '',
            '123456',
            $this->createMock(UrlGeneratorInterface::class),
            $entityManager,
            $this->createMock(LoggerInterface::class),
            new StubManagePurchase(),
            $this->buildCurrencyManager('CZK'),
            $paymentActionLogger,
            'test',
        );

        try {
            $gpWebpay->getPayLink($purchase);
            $this->fail('Expected getPayLink() to refuse a non-positive total');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('is not positive', $e->getMessage());
        }

        $this->assertCount(0, $paymentActionLogger->calls);
    }

    public static function nonPositiveTotalProvider(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-10.0];
    }

    private function buildCurrencyManager(string $iso = 'CZK'): CurrencyManager
    {
        $currencyManager = $this->createMock(CurrencyManager::class);
        $currency = (new Currency())->setName($iso);
        $currencyManager->method('get')->willReturn($currency);
        $currencyManager->method('getForPurchase')->willReturn($currency);

        return $currencyManager;
    }
}

readonly class StubManagePurchase extends ManagePurchase
{
    public function __construct()
    {
    }

    public function preparePrices(Purchase $purchase): Purchase
    {
        return $purchase;
    }
}
