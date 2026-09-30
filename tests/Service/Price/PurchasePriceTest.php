<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;
use PHPUnit\Framework\Attributes\DataProviderExternal;

class PurchasePriceTest extends PriceCalculationTestCase
{
    #[DataProviderExternal(PurchasePriceDataProvider::class, 'basicTotals')]
    public function testBasicTotals(
        array    $ppv,
        VatCalc  $vatCalc,
        DiscCalc $discCalc,
        Currency $currency,
        float    $expectedPurchasePrice,
    ): void
    {
        // ARRANGE
        $purchase = $this->createPurchase($ppv, clientDiscount: null, vouchers: null);

        // ACT
        $pp = $this->createPurchasePrice($purchase, $vatCalc, $discCalc, $currency);

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedPurchasePrice,
            $pp->getPrice(),
            0.001,
            "Purchase price calculation mismatch"
        );
    }

    #[DataProviderExternal(PurchasePriceDataProvider::class, 'servicePrices')]
    public function testServicePrices(
        array           $ppv,
        VatCalc         $vatCalc,
        DiscCalc        $discCalc,
        Currency        $currency,
        ?Transportation $transportation,
        ?PaymentType    $paymentType,
        float           $expectedPurchasePrice,
        float           $expectedTransportationPrice,
        float           $expectedPaymentPrice,
    ): void
    {
        // ARRANGE
        $purchase = $this->createPurchase($ppv, clientDiscount: null, vouchers: null);
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getPaymentType')->willReturn($paymentType);
        $this->handlingPriceRepository->method('GetByDate')
            ->willReturnCallback(function ($entity) {
                return $entity->getHandlingPrices()->first();
            });


        // ACT
        $pp = $this->createPurchasePrice($purchase, $vatCalc, $discCalc, $currency);

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedPurchasePrice,
            $pp->getPrice(true),
            0.001,
            "Purchase price calculation mismatch"
        );
        $this->assertEqualsWithDelta(
            $expectedTransportationPrice,
            $pp->getTransportationPrice(),
            0.001,
            "Transportation price calculation mismatch"
        );
        $this->assertEqualsWithDelta(
            $expectedPaymentPrice,
            $pp->getPaymentPrice(),
            0.001,
            "Payment price calculation mismatch"
        );
    }

    #[DataProviderExternal(PurchasePriceDataProvider::class, 'discounts')]
    public function testDiscounts(
        array    $ppv,
        VatCalc  $vatCalc,
        DiscCalc $discCalc,
        Currency $currency,
        float    $clientDiscount,
        float    $expectedPurchasePrice,
    ): void
    {
        // ARRANGE
        $purchase = $this->createPurchase($ppv, $clientDiscount, vouchers: null);

        // ACT
        $pp = $this->createPurchasePrice($purchase, $vatCalc, $discCalc, $currency);

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedPurchasePrice,
            $pp->getPrice(),
            0.001,
            "Purchase price calculation mismatch"
        );
    }

    #[DataProviderExternal(PurchasePriceDataProvider::class, 'comboUseCase')]
    public function testComboUseCase(
        array           $ppv,
        ?Transportation $transportation,
        ?PaymentType    $paymentType,

        float           $expectedSumPV,
        float           $expectedPriceByVat15,
        float           $expectedPriceByVat21,
        float           $expectedTotalWithServices,
        float           $expectedTotalWithServicesEUR,

        float           $clientDiscount,
        float           $withClientDiscountExpectedSumPV,
        float           $withClientDiscountExpectedPriceByVat15,
        float           $withClientDiscountExpectedPriceByVat21,
        float           $withClientDiscountExpectedTotalWithServices,
        float           $withClientDiscountExpectedTotalWithServicesEUR,
    ): void
    {
        // ARRANGE
        $purchase = $this->createPurchase($ppv, $clientDiscount, vouchers: null);
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getPaymentType')->willReturn($paymentType);
        $this->handlingPriceRepository->method('GetByDate')
            ->willReturnCallback(function ($entity) {
                return $entity->getHandlingPrices()->first();
            });

        // ACT
        $pp = $this->createPurchasePrice($purchase, VatCalc::WithVAT, DiscCalc::OnlyProductDiscount, FactoryUtil::czk());

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedSumPV,
            $pp->getPrice(),
            0.001,
            "Sum of product variant prices mismatch"
        );

        $this->assertEqualsWithDelta(
            $expectedPriceByVat15,
            $pp->getPrice(vat: 15),
            0.001,
            "Price for 15% VAT products mismatch"
        );

        $this->assertEqualsWithDelta(
            $expectedPriceByVat21,
            $pp->getPrice(vat: 21.0),
            0.001,
            "Price for 21% VAT products mismatch"
        );

        $this->assertEqualsWithDelta(
            $expectedTotalWithServices,
            $pp->getPrice(true),
            0.001,
            "Total price with services mismatch"
        );

        $this->assertEqualsWithDelta(
            $expectedTotalWithServices,
            $pp->getMoney(true)->getValue(),
            0.001,
            "getMoney() value should match getPrice() value"
        );
        $this->assertSame('CZK', $pp->getMoney(true)->getIso(), "getMoney() should carry the current currency's ISO code");

        $pp->setCurrency(FactoryUtil::eur());
        $this->assertEqualsWithDelta(
            $expectedTotalWithServicesEUR,
            $pp->getPrice(true),
            0.001,
            "Total price with services mismatch after currency change"
        );

        $this->assertEqualsWithDelta(
            $expectedTotalWithServicesEUR,
            $pp->getMoney(true)->getValue(),
            0.001,
            "getMoney() value should match getPrice() value after currency change"
        );
        $this->assertSame('EUR', $pp->getMoney(true)->getIso(), "getMoney() should follow setCurrency()");

        $pp->setCurrency(FactoryUtil::czk());
        $pp->setDiscountCalculationType(DiscCalc::WithDiscount);
        $this->assertEqualsWithDelta(
            $withClientDiscountExpectedSumPV,
            $pp->getPrice(true),
            0.001,
            "Total price with services mismatch after discount calculation type change"
        );

        $this->assertEqualsWithDelta(
            $withClientDiscountExpectedPriceByVat15,
            $pp->getPrice(vat: 15),
            0.001,
            "Price for 15% VAT products mismatch"
        );

        $this->assertEqualsWithDelta(
            $withClientDiscountExpectedPriceByVat21,
            $pp->getPrice(vat: 21.0),
            0.001,
            "Price for 21% VAT products mismatch"
        );

        $this->assertEqualsWithDelta(
            $withClientDiscountExpectedTotalWithServices,
            $pp->getPrice(true),
            0.001,
            "Total price with services mismatch"
        );

        $pp->setCurrency(FactoryUtil::eur());
        $this->assertEqualsWithDelta(
            $withClientDiscountExpectedTotalWithServicesEUR,
            $pp->getPrice(true),
            0.001,
            "Total price with services mismatch after currency change"
        );
    }

    #[DataProviderExternal(PurchasePriceDataProvider::class, 'ppvCustomPrice')]
    public function testPpvCustomPriceInPurchase(
        array    $ppv,
        VatCalc  $vatCalc,
        DiscCalc $discCalc,
        Currency $currency,
        ?float   $clientDiscount,
        float    $expectedPurchasePrice,
    ): void
    {
        // ARRANGE
        $purchase = $this->createPurchase($ppv, $clientDiscount, vouchers: null);

        // ACT
        $pp = $this->createPurchasePrice($purchase, $vatCalc, $discCalc, $currency);

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedPurchasePrice,
            $pp->getPrice(),
            0.001,
            "Purchase price with PPV custom price mismatch"
        );
    }

    /**
     * Regression test: getDiscountMoney() must carry the exact same numeric value as
     * getDiscountValue() (just tagged with the currency), never a second conversion of it.
     * discountValue is already converted (summed from ProductVariantPrice::getDiscountValue(),
     * which converts using the same conversion rate) - converting it again in getDiscountMoney()
     * silently shrank the reported discount by another factor of the conversion rate.
     */
    public function testDiscountMoneyMatchesDiscountValueRegardlessOfCurrency(): void
    {
        $ppv = [
            [
                'amount' => 11,
                'prices' => [
                    1 => [
                        'price' => FactoryUtil::makePrice(100, 21, 1, discount: 0),
                        'discounted' => FactoryUtil::makePrice(100, 21, 1, discount: 10),
                    ],
                ],
            ],
        ];
        $purchase = $this->createPurchase($ppv, clientDiscount: null, vouchers: null);

        $pp = $this->createPurchasePrice($purchase, VatCalc::WithVAT, DiscCalc::WithDiscount, FactoryUtil::czk());

        $this->assertGreaterThan(0.0, $pp->getDiscountValue(), 'Sanity check: discount should be non-zero');
        $this->assertEqualsWithDelta($pp->getDiscountValue(), $pp->getDiscountMoney()->getValue(), 0.001);
        $this->assertSame('CZK', $pp->getDiscountMoney()->getIso());

        $pp->setCurrency(FactoryUtil::eur());
        $this->assertEqualsWithDelta($pp->getDiscountValue(), $pp->getDiscountMoney()->getValue(), 0.001);
        $this->assertSame('EUR', $pp->getDiscountMoney()->getIso());
    }

    public function testEmptyPurchase(): void
    {
        // ARRANGE
        $purchase = $this->createPurchase([], clientDiscount: null, vouchers: null);

        // ACT
        $pp = $this->createPurchasePrice($purchase, VatCalc::WithVAT, DiscCalc::OnlyProductDiscount, FactoryUtil::czk());

        // ASSERT
        $this->assertEquals(0.0, $pp->getPrice(), "Empty purchase price should be zero");
        $this->assertEquals(0.0, $pp->getPrice(true), "Empty purchase price with services should be zero");
    }
}