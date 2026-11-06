<?php

namespace Greendot\EshopBundle\Tests\Service;

use DateTimeImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Money\Exception\PurchaseCurrencyMismatchException;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\QrPayment\PayBySquareQrGenerator;
use Greendot\EshopBundle\Service\QRcodeGenerator;
use Greendot\EshopBundle\Service\Vies\ManageVies;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class QRcodeGeneratorTest extends TestCase
{
    private const IBAN = 'CZ3620100000002400327394';
    private const BENEFICIARY = 'Account Holder';

    private string $projectDir;
    private PayBySquareQrGenerator&MockObject $payBySquare;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/qr_generator_test_' . uniqid();
        $this->payBySquare = $this->createMock(PayBySquareQrGenerator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testSkPaymentTypeWithBeneficiaryGetsPayBySquare(): void
    {
        $purchase = $this->purchase('SK', 'EUR');

        $this->payBySquare->expects(self::once())
            ->method('png')
            ->with(self::IBAN, self::isInstanceOf(Money::class), 13571, self::isInstanceOf(DateTimeImmutable::class), self::BENEFICIARY)
            ->willReturn('pay-by-square-png');

        $uri = $this->generator(self::BENEFICIARY)->getUri($purchase);

        self::assertSame('/QRcodes/qr_code_7.png', $uri);
        self::assertSame('pay-by-square-png', file_get_contents($this->projectDir . '/public' . $uri));
    }

    public function testCzPaymentTypeKeepsSpayd(): void
    {
        $this->payBySquare->expects(self::never())->method('png');

        $uri = $this->generator(self::BENEFICIARY)->getUri($this->purchase('CZ', 'CZK'));

        self::assertSame($this->spaydPng('CZK'), file_get_contents($this->projectDir . '/public' . $uri));
    }

    public function testSkPaymentTypeWithoutBeneficiaryKeepsSpayd(): void
    {
        $this->payBySquare->expects(self::never())->method('png');
        $this->logger->expects(self::never())->method('error');

        $uri = $this->generator('')->getUri($this->purchase('SK', 'EUR'));

        self::assertSame($this->spaydPng('EUR'), file_get_contents($this->projectDir . '/public' . $uri));
    }

    public function testPayBySquareFailureIsLoggedAndFallsBackToSpayd(): void
    {
        $this->payBySquare->method('png')->willThrowException(new RuntimeException('xz not found'));
        $this->logger->expects(self::once())->method('error');

        $uri = $this->generator(self::BENEFICIARY)->getUri($this->purchase('SK', 'EUR'));

        self::assertSame($this->spaydPng('EUR'), file_get_contents($this->projectDir . '/public' . $uri));
    }

    public function testCurrencyMismatchStillThrows(): void
    {
        $this->payBySquare->expects(self::never())->method('png');

        $purchase = $this->purchase('SK', 'EUR');
        $purchase->method('getCurrency')->willReturn($this->currency('CZK'));

        $this->expectException(PurchaseCurrencyMismatchException::class);

        $this->generator(self::BENEFICIARY)->getUri($purchase);
    }

    private function generator(string $beneficiaryName): QRcodeGenerator
    {
        return new QRcodeGenerator(
            new Filesystem(),
            new RequestStack(),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(CurrencyManager::class),
            $this->managePurchase(),
            $this->payBySquare,
            $this->logger,
            $this->projectDir,
            $beneficiaryName,
        );
    }

    /** ManagePurchase is readonly, so it can't be doubled: use the real one with mocked collaborators. */
    private function managePurchase(): ManagePurchase
    {
        $priceCalculator = $this->createMock(PurchasePrice::class);
        $priceCalculator->method('getMoney')->willReturn(new Money(12.5, 'EUR'));
        $priceCalculator->method('getPaymentMoney')->willReturn(new Money(0.0, 'EUR'));

        $priceFactory = $this->createMock(PurchasePriceFactory::class);
        $priceFactory->method('create')->willReturn($priceCalculator);

        return new ManagePurchase(
            $this->createMock(CurrencyManager::class),
            $priceFactory,
            $this->createMock(ProductVariantPriceFactory::class),
            $this->createMock(PurchaseRepository::class),
            new ManageVies($this->createMock(LoggerInterface::class)),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(ParcelServiceProviderInterface::class),
            $this->createMock(WorkflowInterface::class),
        );
    }

    private function purchase(string $country, string $iso): Purchase&MockObject
    {
        $paymentType = (new PaymentType())
            ->setCountry($country)
            ->setIban(self::IBAN)
            ->setCurrency($this->currency($iso));

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn(7);
        $purchase->method('getOrderNumber')->willReturn(13571);
        $purchase->method('getPaymentType')->willReturn($paymentType);
        $purchase->method('getTotalMoney')->willReturn(new Money(12.5, $iso));

        return $purchase;
    }

    private function currency(string $iso): Currency
    {
        $currency = $this->createMock(Currency::class);
        $currency->method('getIso')->willReturn($iso);

        return $currency;
    }

    /** The code the generator has always produced, built independently of it. */
    private function spaydPng(string $iso): string
    {
        $today = (new DateTimeImmutable('now'))->format('Ymd');

        return (new Builder(
            writer: new PngWriter(),
            validateResult: false,
            data: 'SPD*1.0*ACC:' . self::IBAN . "*AM:12.50*CC:$iso*DT:$today*X-VS:13571*X-KS:308",
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();
    }
}
