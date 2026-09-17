<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;

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
            $variant, $currency, $vatCalc, $discCalc, $amount,
        );


        $this->assertEqualsWithDelta(
            $expectedPrice,
            $pvp->getPrice(),
            0.01,
            "Price calculation mismatch",
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
            $variant, $currency, $vatCalc, $discCalc, $amount,
        );

        $this->assertEqualsWithDelta(
            $expectedPrice,
            $pvp->getPrice(),
            0.01,
            "Price calculation mismatch",
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
            $variant, $currencyCZK, $vatCalc, $discCalc, $amount,
        );

        $this->assertEqualsWithDelta(
            $expectedPriceCZK,
            $pvp->getPrice(),
            0.01,
            "Price calculation mismatch for CZK",
        );

        $pvp->setCurrency($currencyEUR);

        $this->assertEqualsWithDelta(
            $expectedPriceEUR,
            $pvp->getPrice(),
            0.01,
            "Price calculation mismatch for EUR",
        );
    }

    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'ppvCustomPrice')]
    public function testPpvCustomPriceIsCalculatedCorrectly(
        Price    $price,
        int      $amount,
        VatCalc  $vatCalc,
        Currency $currency,
        DiscCalc $discCalc,
        float    $expectedPrice,
    ): void
    {
        // ARRANGE
        $variant = $this->createMock(PurchaseProductVariant::class);
        $variant->method('getAmount')->willReturn($amount);
        $variant->method('getPrice')->willReturn($price);

        // ACT
        $pvp = $this->createProductVariantPrice($variant, $currency, $vatCalc, $discCalc, amount: null);

        // ASSERT
        $this->assertEqualsWithDelta($expectedPrice, $pvp->getPrice(), 0.01);
    }

    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'ppvCustomPriceInvalidCases')]
    public function testInvalidPpvCustomPriceInputThrows(
        Price    $price,
        int      $amount,
        VatCalc  $vatCalc,
        DiscCalc $discCalc,
    ): void
    {
        $this->expectException(\Throwable::class);

        $variant = $this->createMock(PurchaseProductVariant::class);
        $variant->method('getAmount')->willReturn($amount);
        $variant->method('getPrice')->willReturn($price);

        $this->createProductVariantPrice($variant, FactoryUtil::czk(), $vatCalc, $discCalc, null);
    }
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'discountCombinationHighest')]
    public function testHighestDiscountCombinationStrategy(
        string   $productType,
        array    $prices,
        int      $amount,
        VatCalc  $vatCalc,
        Currency $currency,
        DiscCalc $discCalc,
        ?float   $clientDiscount,
        float    $expectedPrice,
    ): void {
        $variant = $this->createVariant($productType, $amount, $prices, $clientDiscount);

        $pvp = $this->createProductVariantPrice(
            $variant,
            $currency,
            $vatCalc,
            $discCalc,
            $amount,
            new \Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\HighestDiscountStrategy(),
        );

        $this->assertEqualsWithDelta(
            $expectedPrice,
            $pvp->getPrice(),
            0.01,
            'Highest discount strategy: wrong price',
        );
    }

    /**
     * createFromPrefetchedPrices() must produce the same prices create() would for the same
     * underlying rows — both for an amount matching one tier exactly (uses only that tier, per
     * the DESC-first-match cascading logic) and for amount=null (resolves the lowest tier without
     * a getMinimalAmount() query). Regression test for a bug where the ceiling-filter closure
     * referenced $this while declared `static`, which throws the instant it's actually invoked.
     */
    public function testCreateFromPrefetchedPricesMatchesCreate(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $priceTen = FactoryUtil::makePrice(90, 0, minimalAmount: 10, isPackage: true);
        $priceOne = FactoryUtil::makePrice(100, 0, minimalAmount: 1);
        $variant = $this->createMock(ProductVariant::class);

        // DESC by minimalAmount, same shape/order findPricesByDateAndProductVariantNew() returns.
        $prefetchedPrices = [
            10 => ['price' => $priceTen],
            1  => ['price' => $priceOne],
        ];

        // amount = 17 (cascades across both tiers): matches the existing 'tierPrice' scenario.
        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant, $this->anything(), 17)
            ->willReturn($prefetchedPrices);
        $viaCreate = $this->productVariantPriceFactory->create(
            pv: $variant, currencyOrConversionRate: FactoryUtil::czk(), amount: 17,
        );
        $viaPrefetched = $this->productVariantPriceFactory->createFromPrefetchedPrices(
            pv: $variant, prefetchedPrices: $prefetchedPrices, currencyOrConversionRate: FactoryUtil::czk(), amount: 17,
        );
        $this->assertEqualsWithDelta($viaCreate->getPrice(), $viaPrefetched->getPrice(), 0.01);
        $this->assertEqualsWithDelta(1600.0, $viaPrefetched->getPrice(), 0.01);

        // amount = 10 (exact tier match): must use only that tier's own row.
        $viaPrefetchedTier = $this->productVariantPriceFactory->createFromPrefetchedPrices(
            pv: $variant, prefetchedPrices: $prefetchedPrices, currencyOrConversionRate: FactoryUtil::czk(), amount: 10,
        );
        $this->assertEqualsWithDelta(900.0, $viaPrefetchedTier->getPrice(), 0.01);

        // amount = null: must resolve minAmount from the map (lowest key = 1) without querying.
        $this->priceRepository->expects($this->never())->method('getMinimalAmount');
        $viaPrefetchedBase = $this->productVariantPriceFactory->createFromPrefetchedPrices(
            pv: $variant, prefetchedPrices: $prefetchedPrices, currencyOrConversionRate: FactoryUtil::czk(),
        );
        $this->assertEqualsWithDelta(100.0, $viaPrefetchedBase->getPrice(), 0.01);
        $this->assertSame(1, $viaPrefetchedBase->getMinimalAmount());
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
            'pv'  => $this->createProductVariantMock($clientDiscount),
            'ppv' => $this->createPurchaseProductVariantMock($amount, $clientDiscount),
        };
        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant)
            ->willReturn($prices)
        ;

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
    private function createPurchaseProductVariantMock(int $amount, ?float $clientDiscount): PurchaseProductVariant&MockObject
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
