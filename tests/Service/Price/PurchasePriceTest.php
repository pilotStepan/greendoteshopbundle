<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\MockObject\MockObject;

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
        $purchase = $this->createPurchase($ppv, clientDiscount: null);

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
        $purchase = $this->createPurchase($ppv, clientDiscount: null);
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
        $purchase = $this->createPurchase($ppv, $clientDiscount);

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
        $purchase = $this->createPurchase($ppv, $clientDiscount);
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

    // ---HELPERS---
    private function createPurchase(array $ppv, float|null $clientDiscount): Purchase&MockObject
    {
        $purchase = $this->createMock(Purchase::class);
        $purchaseProductVariants = [];
        $variantPrices = [];

        foreach ($ppv as $index => $variant) {
            $purchaseProductVariant = $this->createMock(PurchaseProductVariant::class);
            $productVariantMock = $this->createMock(ProductVariant::class);
            $productVariantMock->_index = $index;

            $purchaseProductVariant->method('getAmount')->willReturn($variant['amount']);
            $purchaseProductVariant->method('getProductVariant')->willReturn($productVariantMock);
            $purchaseProductVariant->method('getPurchase')->willReturn($purchase);

            $purchaseProductVariants[] = $purchaseProductVariant;
            $variantPrices[$index] = $variant['prices'];
        }

        $purchase->method('getProductVariants')
            ->willReturn(new ArrayCollection($purchaseProductVariants));

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->willReturnCallback(function ($productVariant) use ($variantPrices) {
                if (isset($productVariant->_index)) {
                    return $variantPrices[$productVariant->_index];
                }
                return null;
            });

        if ($clientDiscount) {
            $clientDiscountMock = $this->createMock(ClientDiscount::class);
            $clientDiscountMock->method('getDiscount')->willReturn($clientDiscount);
            $purchase->method('getClientDiscount')->willReturn($clientDiscountMock);
        }

        return $purchase;
    }

    private function createPurchasePrice(
        Purchase $purchase,
        VatCalc  $vatCalc,
        DiscCalc $discCalc,
        Currency $currency,
    )
    {
        return new PurchasePrice(
            $purchase,
            $vatCalc,
            $discCalc,
            $currency,
            $this->productVariantPriceFactory,
            $this->currencyRepository,
            $this->handlingPriceRepository,
            $this->priceUtils
        );
    }
}