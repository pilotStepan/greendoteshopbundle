<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
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

    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'onlyProductDiscount')]
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'clientAndProduct')]
    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'afterRegistration')]
    public function testProductVariantPriceDiscounts(
        string   $productType,
        array    $prices,
        int      $amount,
        VatCalc  $vatCalc,
        Currency $currency,
        DiscCalc $discCalc,
        ?float   $clientDiscount,
        float    $expectedDiscountValue,
        float    $expectedPrice,
    ): void
    {
        $variant = $this->createVariant($productType, $amount, $prices, $clientDiscount);

        $pvp = $this->createProductVariantPrice(
            $variant, $amount, $currency, $vatCalc, $discCalc
        );

        $this->assertEquals(
            $expectedDiscountValue,
            $pvp->getDiscountValue(),
            "Discount value mismatch"
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

    #[DataProviderExternal(ProductVariantPriceDataProvider::class, 'mixedVatException')]
    public function testProductVariantPriceMixedVatException(
        string   $productType,
        array    $prices,
        int      $amount,
        VatCalc  $vatCalc,
        Currency $currency,
        DiscCalc $discCalc,
        ?float   $clientDiscount,
        string   $expectExceptionMsg,
    )
    {
        $variant = $this->createVariant($productType, $amount, $prices, $clientDiscount);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($expectExceptionMsg);

        $this->createProductVariantPrice(
            $variant, $amount, $currency, $vatCalc, $discCalc
        );
    }
}
