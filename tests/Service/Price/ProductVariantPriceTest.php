<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;

class ProductVariantPriceTest extends PriceCalculationTestCase
{
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'withVat')]
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'withoutVat')]
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'onlyVat')]
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'minPrice')]
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'tierPrice')]
    public function testProductVariantPrice(
        string   $productType,
        array    $prices,
        int      $amount,
        VatCalc  $vatCalc,
        Currency $currency,
        DiscCalc $discCalc,
        ?float   $clientDiscount,
        float    $expectedPrice,
    ): void
    {
        $variant = $this->createVariant($productType, $amount, $prices, $clientDiscount);

        $pvp = $this->createProductVariantPrice(
            $variant, $amount, $currency, $vatCalc, $discCalc
        );


        $this->assertEqualsWithDelta(
            $expectedPrice,
            $pvp->getPrice(),
            0.01,
            "Price calculation mismatch"
        );
    }

    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'clientAndProduct')]
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'onlyProductDiscount')]
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'afterRegistration')]
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'discountEdge')]
    public function testProductVariantPriceDiscounts(
        string   $productType,
        array    $prices,
        int      $amount,
        VatCalc  $vatCalc,
        Currency $currency,
        DiscCalc $discCalc,
        ?float   $clientDiscount,
        ?float   $expectedPrice,
    ): void
    {
        $variant = $this->createVariant($productType, $amount, $prices, $clientDiscount);

        $pvp = $this->createProductVariantPrice(
            $variant, $amount, $currency, $vatCalc, $discCalc
        );

        $this->assertEqualsWithDelta(
            $expectedPrice,
            $pvp->getPrice(),
            0.01,
            "Price calculation mismatch"
        );
    }

    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'currencySetter')]
    public function testProductVariantPriceCurrencySetter(
        string   $productType,
        array    $prices,
        int      $amount,
        VatCalc  $vatCalc,
        Currency $currencyCZK,
        Currency $currencyEUR,
        DiscCalc $discCalc,
        ?float   $clientDiscount,
        float    $expectedPriceCZK,
        float    $expectedPriceEUR,
    ): void
    {
        $variant = $this->createVariant($productType, $amount, $prices, $clientDiscount);

        $pvp = $this->createProductVariantPrice(
            $variant, $amount, $currencyCZK, $vatCalc, $discCalc
        );

        $this->assertEqualsWithDelta(
            $expectedPriceCZK,
            $pvp->getPrice(),
            0.01,
            "Price calculation mismatch for CZK"
        );

        $pvp->setCurrency($currencyEUR);

        $this->assertEqualsWithDelta(
            $expectedPriceEUR,
            $pvp->getPrice(),
            0.01,
            "Price calculation mismatch for EUR"
        );
    }

// Commented out - missing provider
//    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'mixedVatException')]
//    public function testProductVariantPriceMixedVatException(
//        string   $productType,
//        array    $prices,
//        int      $amount,
//        VatCalc  $vatCalc,
//        Currency $currency,
//        DiscCalc $discCalc,
//        ?float   $clientDiscount,
//        string   $expectExceptionMsg,
//    )
//    {
//        $variant = $this->createVariant($productType, $amount, $prices, $clientDiscount);
//
//        $this->expectException(\Exception::class);
//        $this->expectExceptionMessage($expectExceptionMsg);
//
//        $this->createProductVariantPrice(
//            $variant, $amount, $currency, $vatCalc, $discCalc
//        );
//    }

    /**
     * Create a variant based on a product type
     */
    private function createVariant(string $productType, int $amount, array $prices, ?float $clientDiscount): ProductVariant|PurchaseProductVariant
    {
        $variant = match ($productType) {
            'pv' => $this->createProductVariantMock($clientDiscount),
            'ppv' => $this->createPurchaseProductVariantMock($amount, $clientDiscount),
        };
        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant)
            ->willReturn($prices);

        return $variant;
    }

    /**
     * Create a ProductVariant mock with client discount if needed
     */
    private function createProductVariantMock(?float $clientDiscount): ProductVariant
    {
        $variant = $this->createMock(ProductVariant::class);

        if ($clientDiscount !== null) {
            $this->setupClientDiscountForProductVariant($clientDiscount);
        } else {
            $this->security->method('getUser')->willReturn(null);
        }

        return $variant;
    }

    /**
     * Create a PurchaseProductVariant mock with necessary dependencies
     */
    private function createPurchaseProductVariantMock(int $amount, ?float $clientDiscount): PurchaseProductVariant
    {
        $variant = $this->createMock(PurchaseProductVariant::class);
        $productVariantMock = $this->createMock(ProductVariant::class);

        $variant->method('getProductVariant')->willReturn($productVariantMock);
        $variant->method('getAmount')->willReturn($amount);

        if ($clientDiscount !== null) {
            $purchase = $this->createMock(Purchase::class);
            $clientDiscountObj = $this->createMock(ClientDiscount::class);

            $clientDiscountObj->method('getDiscount')->willReturn($clientDiscount);
            $purchase->method('getClientDiscount')->willReturn($clientDiscountObj);
            $variant->method('getPurchase')->willReturn($purchase);
        }

        return $variant;
    }
}
