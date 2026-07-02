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


        $pp->setCurrency(FactoryUtil::eur());
        $this->assertEqualsWithDelta(
            $expectedTotalWithServicesEUR,
            $pp->getPrice(true),
            0.001,
            "Total price with services mismatch after currency change"
        );

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

    public function testEmptyPurchase(): void
    {
        // ARRANGE
        $purchase = $this->createPurchase([], clientDiscount: null, vouchers: null);

        // ACT
        $pp = $this->createPurchasePrice($purchase, VatCalc::WithVAT, DiscCalc::OnlyProductDiscount, FactoryUtil::czk());

        // ASSERT
        $this->assertEquals(0.0, $pp->getPrice(), "Empty purchase price should be zero");
        $this->assertEquals(0.0, $pp->getPrice(true), "Empty purchase price with services should be zero");
        $this->assertNull($pp->getTransportationPrice(), "No transportation configured -> null, not 0");
        $this->assertNull($pp->getPaymentPrice(), "No payment type configured -> null, not 0");
    }

    public function testVouchersUsedValueIsZeroWhenNoVouchersApplied(): void
    {
        $ppv = [['amount' => 1, 'prices' => [['price' => FactoryUtil::makePrice(100, 0), 'discounted' => null]]]];
        $purchase = $this->createPurchase($ppv, clientDiscount: null, vouchers: null);

        $pp = $this->createPurchasePrice($purchase, VatCalc::WithoutVAT, DiscCalc::WithoutDiscount, FactoryUtil::czk());

        $this->assertSame(0.0, $pp->getVouchersUsedValue());
    }

    public function testVouchersUsedValueReflectsAppliedVoucherAmount(): void
    {
        $ppv = [['amount' => 1, 'prices' => [['price' => FactoryUtil::makePrice(100, 0), 'discounted' => null]]]];
        $purchase = $this->createPurchase($ppv, clientDiscount: null, vouchers: [FactoryUtil::makeVoucher(30)]);

        $pp = $this->createPurchasePrice($purchase, VatCalc::WithoutVAT, DiscCalc::WithoutDiscount, FactoryUtil::czk());
        $pp->setVoucherCalculationType(\Greendot\EshopBundle\Enum\VoucherCalculationType::WithVoucher);

        $this->assertSame(30.0, $pp->getVouchersUsedValue());
        $this->assertEqualsWithDelta(70.0, $pp->getPrice(), 0.01, 'voucher must actually reduce the price');
    }

    public function testSetVatCalculationTypeOnPurchasePriceRecalculatesVariantPrices(): void
    {
        // PurchasePrice::setVatCalculationType() must propagate down to each ProductVariantPrice,
        // not just flip its own field — nothing in the suite called this method before.
        $ppv = [['amount' => 1, 'prices' => [['price' => FactoryUtil::makePrice(100, 21), 'discounted' => null]]]];
        $purchase = $this->createPurchase($ppv, clientDiscount: null, vouchers: null);

        $pp = $this->createPurchasePrice($purchase, VatCalc::WithVAT, DiscCalc::WithoutDiscount, FactoryUtil::czk());
        $this->assertEqualsWithDelta(121.0, $pp->getPrice(), 0.01);

        $pp->setVatCalculationType(VatCalc::WithoutVAT);
        $this->assertEqualsWithDelta(100.0, $pp->getPrice(), 0.01);
    }

    public function testSetVatCalculationTypeOnPurchasePriceIsBlockedForVatExemptPurchaseUnlessForced(): void
    {
        // ProductVariantPriceFactory::create() already forces WithoutVAT at construction time
        // for an exempt purchase (line 71), so the guard can only be observed by trying to move
        // *away* from WithoutVAT afterwards, not by re-applying WithoutVAT.
        $ppv = [['amount' => 1, 'prices' => [['price' => FactoryUtil::makePrice(100, 21), 'discounted' => null]]]];
        $purchase = $this->createPurchase($ppv, clientDiscount: null, vouchers: null);
        $purchase->method('isVatExempted')->willReturn(true);

        $pp = $this->createPurchasePrice($purchase, VatCalc::WithoutVAT, DiscCalc::WithoutDiscount, FactoryUtil::czk());
        $this->assertEqualsWithDelta(100.0, $pp->getPrice(), 0.01);

        $pp->setVatCalculationType(VatCalc::WithVAT);
        $this->assertEqualsWithDelta(100.0, $pp->getPrice(), 0.01, 'VAT-exempt purchase must ignore an unforced VAT type change');

        $pp->setVatCalculationType(VatCalc::WithVAT, force: true);
        $this->assertEqualsWithDelta(121.0, $pp->getPrice(), 0.01, 'force: true must override VAT exemption');
    }

    public function testDiscountValueAndPercentageAggregateAcrossVariants(): void
    {
        // Neither getDiscountValue() nor getDiscountPercentage() was asserted anywhere before.
        $ppv = [
            ['amount' => 1, 'prices' => [1 => [
                'price' => FactoryUtil::makePrice(100, 0, discount: 0),
                'discounted' => FactoryUtil::makePrice(100, 0, discount: 20),
            ]]],
            ['amount' => 1, 'prices' => [1 => [
                'price' => FactoryUtil::makePrice(100, 0, discount: 0),
                'discounted' => FactoryUtil::makePrice(100, 0, discount: 40),
            ]]],
        ];
        $purchase = $this->createPurchase($ppv, clientDiscount: null, vouchers: null);

        $pp = $this->createPurchasePrice($purchase, VatCalc::WithoutVAT, DiscCalc::OnlyProductDiscount, FactoryUtil::czk());

        // discountValue: 20 (20% of 100) + 40 (40% of 100) = 60
        $this->assertEqualsWithDelta(60.0, $pp->getDiscountValue(), 0.01);
        // discountPercentage: average of the two variants' 20% and 40% = 30%
        $this->assertEqualsWithDelta(30.0, $pp->getDiscountPercentage(), 0.01);
    }
}