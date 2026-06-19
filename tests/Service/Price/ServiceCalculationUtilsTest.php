<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\HandlingPrice;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;

class ServiceCalculationUtilsTest extends PriceCalculationTestCase
{
    private function makeHandlingPrice(float $price, int $vat, ?float $freeFromPrice): HandlingPrice
    {
        return (new HandlingPrice())
            ->setPrice($price)
            ->setVat($vat)
            ->setFreeFromPrice($freeFromPrice)
            ->setValidFrom(new \DateTime('-1 day'))
            ->setValidUntil(new \DateTime('+1 day'));
    }

    // -------------------------------------------------------------------------
    // calculateServicePrice
    // -------------------------------------------------------------------------

    public function testCalculateServicePriceReturnsZeroWhenNoHandlingPrice(): void
    {
        $this->handlingPriceRepository->method('getByDate')->willReturn(null);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice($service, FactoryUtil::czk());

        $this->assertEqualsWithDelta(0.0, $result, 0.001);
    }

    public function testCalculateServicePriceReturnsZeroWhenPriceLessThanOne(): void
    {
        $hp = $this->makeHandlingPrice(0.5, 21, null);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice($service, FactoryUtil::czk());

        $this->assertEqualsWithDelta(0.0, $result, 0.001);
    }

    public function testCalculateServicePriceReturnsZeroWhenBasketMeetsThreshold(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, 500.0);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            $service, FactoryUtil::czk(), theoreticalAmount: 500.0
        );

        $this->assertEqualsWithDelta(0.0, $result, 0.001);
    }

    public function testCalculateServicePriceReturnsZeroWhenBasketExceedsThreshold(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, 500.0);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            $service, FactoryUtil::czk(), theoreticalAmount: 600.0
        );

        $this->assertEqualsWithDelta(0.0, $result, 0.001);
    }

    public function testCalculateServicePriceDoesNotFreeWhenBelowThreshold(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, 500.0);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            $service, FactoryUtil::czk(), VatCalculationType::WithoutVAT, theoreticalAmount: 499.99
        );

        $this->assertEqualsWithDelta(100.0, $result, 0.001);
    }

    public function testCalculateServicePriceWithoutVat(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, null);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            $service, FactoryUtil::czk(), VatCalculationType::WithoutVAT
        );

        $this->assertEqualsWithDelta(100.0, $result, 0.001);
    }

    public function testCalculateServicePriceWithVat(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, null);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            $service, FactoryUtil::czk(), VatCalculationType::WithVAT
        );

        $this->assertEqualsWithDelta(121.0, $result, 0.001);
    }

    public function testCalculateServicePriceOnlyVat(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, null);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            $service, FactoryUtil::czk(), VatCalculationType::OnlyVAT
        );

        $this->assertEqualsWithDelta(21.0, $result, 0.001);
    }

    public function testCalculateServicePriceAppliesCurrencyConversion(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, null);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            $service, FactoryUtil::eur(), VatCalculationType::WithVAT
        );

        // 121.0 * 0.04 = 4.84, rounded to 2 decimal places
        $this->assertEqualsWithDelta(4.84, $result, 0.001);
    }

    public function testCalculateServicePriceReturnRawSkipsConversion(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, null);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            service: $service,
            currencyOrConversionRate: FactoryUtil::eur(),
            vatCalculationType: VatCalculationType::WithVAT,
            returnRaw: true,
        );

        $this->assertEqualsWithDelta(121.0, $result, 0.001);
    }

    public function testCalculateServicePriceAcceptsConversionRateDirectly(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, null);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);
        $conversionRate = FactoryUtil::czk()->getConversionRates()->first();

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            $service, $conversionRate, VatCalculationType::WithVAT
        );

        $this->assertEqualsWithDelta(121.0, $result, 0.001);
    }

    public function testCalculateServicePriceWorksForPaymentType(): void
    {
        $hp = $this->makeHandlingPrice(50.0, 15, null);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(PaymentType::class);

        $result = $this->serviceCalculationUtils->calculateServicePrice(
            $service, FactoryUtil::czk(), VatCalculationType::WithVAT
        );

        $this->assertEqualsWithDelta(57.5, $result, 0.001);
    }

    // -------------------------------------------------------------------------
    // getFreeFromPrice
    // -------------------------------------------------------------------------

    public function testGetFreeFromPriceReturnsZeroPointZeroWhenNoHandlingPrice(): void
    {
        $this->handlingPriceRepository->method('getByDate')->willReturn(null);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->getFreeFromPrice($service, FactoryUtil::czk());

        $this->assertEqualsWithDelta(0.0, $result, 0.001);
    }

    public function testGetFreeFromPriceReturnsNullWhenNotConfigured(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, null);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->getFreeFromPrice($service, FactoryUtil::czk());

        $this->assertNull($result);
    }

    public function testGetFreeFromPriceAppliesCurrencyConversion(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, 500.0);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->getFreeFromPrice($service, FactoryUtil::eur());

        // 500.0 * 0.04 = 20.0, rounded to 2 decimal places
        $this->assertEqualsWithDelta(20.0, $result, 0.001);
    }

    public function testGetFreeFromPriceReturnsCzkFreeFromPrice(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, 999.0);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);

        $result = $this->serviceCalculationUtils->getFreeFromPrice($service, FactoryUtil::czk());

        $this->assertEqualsWithDelta(999.0, $result, 0.001);
    }

    public function testGetFreeFromPriceUsesProvidedConversionRate(): void
    {
        $hp = $this->makeHandlingPrice(100.0, 21, 500.0);
        $this->handlingPriceRepository->method('getByDate')->willReturn($hp);
        $service = $this->createMock(Transportation::class);
        $eur = FactoryUtil::eur();
        $conversionRate = $eur->getConversionRates()->first();

        $result = $this->serviceCalculationUtils->getFreeFromPrice($service, $eur, $conversionRate);

        // 500.0 * 0.04 = 20.0
        $this->assertEqualsWithDelta(20.0, $result, 0.001);
    }
}
