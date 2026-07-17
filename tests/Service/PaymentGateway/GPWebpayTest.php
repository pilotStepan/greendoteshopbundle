<?php

namespace Greendot\EshopBundle\Tests\Service\PaymentGateway;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Currency;
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
        $purchase = new Purchase();
        $purchase->setTotalPrice(150.0);

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
            $this->buildCurrencyManager(),
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
        $this->assertNotNull($payment);
    }

    private function buildCurrencyManager(): CurrencyManager
    {
        $currencyManager = $this->createMock(CurrencyManager::class);
        $currency = (new Currency())->setName('CZK');
        $currencyManager->method('get')->willReturn($currency);

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
