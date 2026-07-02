<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\ProductProduct;
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

    public function testGetMinPriceNoConversionReturnsRawValueNotCurrencyConverted(): void
    {
        $variant = $this->createVariant('pv', 1, [[
            'price' => FactoryUtil::makePrice(50, 0, minPrice: 60),
        ]], 0);

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::eur(), VatCalc::WithVAT, DiscCalc::WithoutDiscount, 1);

        // EUR conversion rate is 0.04 (see PriceCalculationTestCase); noConversion must skip it.
        $this->assertSame(60.0, $pvp->getMinPrice(true));
        $this->assertEqualsWithDelta(2.4, $pvp->getMinPrice(false), 0.01);
        // Default argument (no explicit bool) must behave like false, i.e. convert.
        $this->assertEqualsWithDelta(2.4, $pvp->getMinPrice(), 0.01);
    }

    public function testParentProductDiscountIsAdditiveOnTopOfProductAndClientDiscount(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getProduct')->willReturn($product);
        $this->setupClientDiscountForProductVariant(5);

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant)
            ->willReturn([1 => [
                'price' => FactoryUtil::makePrice(100, 20, discount: 0),
                'discounted' => FactoryUtil::makePrice(100, 20, discount: 10),
            ]])
        ;

        $parentProductProduct = $this->createMock(ProductProduct::class);
        $parentProductProduct->method('getChildrenProduct')->willReturn($product);
        $parentProductProduct->method('getDiscount')->willReturn(8);

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithDiscount, 2);
        $pvp->setParentProduct($parentProductProduct);

        // 2x100=200, total discount = combine(10, 5) + 8 = 23%, -23% = 154, +20% VAT = 184.8
        $this->assertEqualsWithDelta(184.8, $pvp->getPrice(), 0.01);
    }

    public function testParentProductDiscountIsAdditiveWithAfterRegistrationBonus(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getProduct')->willReturn($product);
        $this->security->method('getUser')->willReturn(null); // no client -> afterRegistrationBonus (20, per setUp) applies

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant)
            ->willReturn([1 => [
                'discounted' => FactoryUtil::makePrice(100, 20, discount: 10),
            ]])
        ;

        $parentProductProduct = $this->createMock(ProductProduct::class);
        $parentProductProduct->method('getChildrenProduct')->willReturn($product);
        $parentProductProduct->method('getDiscount')->willReturn(8);

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithDiscountPlusAfterRegistrationDiscount, 2);
        $pvp->setParentProduct($parentProductProduct);

        // 2x100=200, total discount = combine(10, 20 bonus) + 8 = 38%, -38% = 124, +20% VAT = 148.8
        $this->assertEqualsWithDelta(148.8, $pvp->getPrice(), 0.01);
    }

    public function testDiscountPercentageFallsBackToZeroNotOneWhenProductHasNoDiscount(): void
    {
        // No 'discounted' price entry at all -> $this->discountPercentage stays null,
        // so getDiscountPercentage() must fall back to 0.0, not silently add 1%.
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getProduct')->willReturn($product);
        $this->setupClientDiscountForProductVariant(5);

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant)
            ->willReturn([['price' => FactoryUtil::makePrice(100, 20)]])
        ;

        $parentProductProduct = $this->createMock(ProductProduct::class);
        $parentProductProduct->method('getChildrenProduct')->willReturn($product);
        $parentProductProduct->method('getDiscount')->willReturn(8);

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithDiscount, 2);
        $pvp->setParentProduct($parentProductProduct);

        // 2x100=200, total discount = combine(0, 5) + 8 = 13%, -13% = 174, +20% VAT = 208.8
        $this->assertEqualsWithDelta(208.8, $pvp->getPrice(), 0.01);
    }

    public function testAfterRegistrationDiscountPercentageFallsBackToZeroNotOneWhenProductHasNoDiscount(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getProduct')->willReturn($product);
        $this->security->method('getUser')->willReturn(null); // no client -> afterRegistrationBonus (20) applies

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant)
            ->willReturn([['price' => FactoryUtil::makePrice(100, 20)]])
        ;

        $parentProductProduct = $this->createMock(ProductProduct::class);
        $parentProductProduct->method('getChildrenProduct')->willReturn($product);
        $parentProductProduct->method('getDiscount')->willReturn(8);

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithDiscountPlusAfterRegistrationDiscount, 2);
        $pvp->setParentProduct($parentProductProduct);

        // 2x100=200, total discount = combine(0, 20 bonus) + 8 = 28%, -28% = 144, +20% VAT = 172.8
        $this->assertEqualsWithDelta(172.8, $pvp->getPrice(), 0.01);
    }

    public function testSetParentProductLookupMissIsSkippedSafely(): void
    {
        // setParentProduct(Product) (not ProductProduct) goes through the repository lookup;
        // when it finds nothing, the guard must return before touching a null ProductProduct.
        $product = $this->createMock(ProductVariant::class);
        $productEntity = $this->createMock(Product::class);
        $product->method('getProduct')->willReturn($productEntity);

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($product)
            ->willReturn([['price' => FactoryUtil::makePrice(100, 0)]])
        ;
        $this->security->method('getUser')->willReturn(null);
        $this->productProductRepository->method('findOneBy')->willReturn(null);

        $pvp = $this->createProductVariantPrice($product, FactoryUtil::czk(), VatCalc::WithoutVAT, DiscCalc::WithoutDiscount, 1);
        $parentProduct = $this->createMock(Product::class);
        $pvp->setParentProduct($parentProduct);

        $this->assertEqualsWithDelta(100.0, $pvp->getPrice(), 0.01);
    }

    public function testSetParentProductIsIgnoredWhenComplementaryProductHasNoDiscount(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getProduct')->willReturn($product);
        $this->security->method('getUser')->willReturn(null);

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant)
            ->willReturn([['price' => FactoryUtil::makePrice(100, 20)]])
        ;

        $parentProductProduct = $this->createMock(ProductProduct::class);
        $parentProductProduct->method('getChildrenProduct')->willReturn($product);
        $parentProductProduct->method('getDiscount')->willReturn(0); // no discount configured

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithDiscount, 2);
        $priceBefore = $pvp->getPrice();
        $pvp->setParentProduct($parentProductProduct);

        $this->assertEqualsWithDelta($priceBefore, $pvp->getPrice(), 0.01, 'A zero-discount complementary product must not change the price');
    }

    public function testSetVatCalculationTypeIsBlockedForVatExemptPurchaseUnlessForced(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('isVatExempted')->willReturn(true);
        $purchase->method('getDateIssue')->willReturn(new \DateTime('now'));

        $variant = $this->createMock(PurchaseProductVariant::class);
        $productVariantMock = $this->createMock(ProductVariant::class);
        $variant->method('getProductVariant')->willReturn($productVariantMock);
        $variant->method('getAmount')->willReturn(1);
        $variant->method('getPurchase')->willReturn($purchase);

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->willReturn([['price' => FactoryUtil::makePrice(100, 21)]])
        ;

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithoutDiscount, null);
        $priceBefore = $pvp->getPrice();

        $pvp->setVatCalculationType(VatCalc::WithoutVAT);
        $this->assertEqualsWithDelta($priceBefore, $pvp->getPrice(), 0.01, 'VAT-exempt purchase must ignore an unforced VAT type change');

        $pvp->setVatCalculationType(VatCalc::WithoutVAT, force: true);
        $this->assertEqualsWithDelta(100.0, $pvp->getPrice(), 0.01, 'force: true must override VAT exemption');
    }

    public function testSetVatCalculationTypeAppliesWhenPurchaseIsNotVatExempt(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('isVatExempted')->willReturn(false);
        $purchase->method('getDateIssue')->willReturn(new \DateTime('now'));

        $variant = $this->createMock(PurchaseProductVariant::class);
        $productVariantMock = $this->createMock(ProductVariant::class);
        $variant->method('getProductVariant')->willReturn($productVariantMock);
        $variant->method('getAmount')->willReturn(1);
        $variant->method('getPurchase')->willReturn($purchase);

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->willReturn([['price' => FactoryUtil::makePrice(100, 21)]])
        ;

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithoutDiscount, null);
        $pvp->setVatCalculationType(VatCalc::WithoutVAT);

        $this->assertEqualsWithDelta(100.0, $pvp->getPrice(), 0.01);
    }

    public function testSetVatCalculationTypeAppliesForPlainProductVariantWithoutFatalError(): void
    {
        // Not a PurchaseProductVariant: the `instanceof` check must short-circuit before
        // touching ->getPurchase(), which ProductVariant does not implement.
        $variant = $this->createVariant('pv', 1, [['price' => FactoryUtil::makePrice(100, 21)]], 0);

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithoutDiscount, 1);
        $pvp->setVatCalculationType(VatCalc::WithoutVAT);

        $this->assertEqualsWithDelta(100.0, $pvp->getPrice(), 0.01);
    }

    public function testSetVatCalculationTypeAppliesWhenPurchaseProductVariantHasNoPurchaseYet(): void
    {
        // getPurchase() returns null (e.g. cart phase) — the nullsafe operator must protect
        // this, defaulting isVatExempted to false so the change is applied.
        $variant = $this->createMock(PurchaseProductVariant::class);
        $productVariantMock = $this->createMock(ProductVariant::class);
        $variant->method('getProductVariant')->willReturn($productVariantMock);
        $variant->method('getAmount')->willReturn(1);
        $variant->method('getPurchase')->willReturn(null);

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->willReturn([['price' => FactoryUtil::makePrice(100, 21)]])
        ;

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithoutDiscount, null);
        $pvp->setVatCalculationType(VatCalc::WithoutVAT);

        $this->assertEqualsWithDelta(100.0, $pvp->getPrice(), 0.01);
    }

    public function testPriceValidUntilIsSetFromTheMatchedPriceTier(): void
    {
        // Guards the `$pass === 1` first-tier bookkeeping: getPriceValidUntil() was never
        // asserted anywhere before, so a broken pass counter went unnoticed.
        $validUntil = new \DateTime('+5 days');
        $price = FactoryUtil::makePrice(100, 0);
        $price->setValidUntil($validUntil);

        $variant = $this->createVariant('pv', 3, [['price' => $price]], 0);
        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithoutVAT, DiscCalc::WithoutDiscount, 3);

        $this->assertEquals($validUntil, $pvp->getPriceValidUntil());
    }

    public function testPriceKeyTakesPrecedenceOverDiscountedKeyWhenBothPresentWithDifferentUnitPrices(): void
    {
        // Regression guard for the `if ($discountedPrice and !$price) $price = $discountedPrice;`
        // fallback: it must only substitute when 'price' is genuinely absent, not whenever
        // 'discounted' is present.
        $variant = $this->createVariant('pv', 1, [1 => [
            'price' => FactoryUtil::makePrice(100, 0, discount: 0),
            'discounted' => FactoryUtil::makePrice(150, 0, discount: 10),
        ]], 0);

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithoutVAT, DiscCalc::WithoutDiscount, 1);

        // Base price must come from the 'price' entry (100), not 'discounted' (150).
        $this->assertEqualsWithDelta(100.0, $pvp->getPrice(), 0.01);
    }

    public function testDiscountValueUnderWithVatIncludesVatOnTheDiscountItself(): void
    {
        // recalculateNoQuery(): under WithVAT, fullDiscountValue must be grossed up by VAT
        // too, not just the price. Base 300, 10% discount = 30 discount, +21% VAT = 36.3.
        $variant = $this->createVariant('pv', 3, [1 => [
            'price' => FactoryUtil::makePrice(100, 21, discount: 0),
            'discounted' => FactoryUtil::makePrice(100, 21, discount: 10),
        ]], 0);

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::OnlyProductDiscount, 3);

        $this->assertEqualsWithDelta(36.3, $pvp->getDiscountValue(), 0.01);
    }

    public function testClientDiscountIsSkippedForRoleApiUsers(): void
    {
        // ROLE_API callers (Simple-ws) are not real Client instances; the clientDiscount
        // lookup must be skipped entirely rather than calling discountService.
        $variant = $this->createMock(ProductVariant::class);
        $apiUser = new \Symfony\Component\Security\Core\User\InMemoryUser('api-caller', null, ['ROLE_API']);
        $this->security->method('getUser')->willReturn($apiUser);
        $this->discountService->expects($this->never())->method('getValidClientDiscount');

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant)
            ->willReturn([['price' => FactoryUtil::makePrice(100, 0, discount: 0)]])
        ;

        $pvp = $this->createProductVariantPrice($variant, FactoryUtil::czk(), VatCalc::WithoutVAT, DiscCalc::WithDiscount, 1);

        $this->assertEqualsWithDelta(100.0, $pvp->getPrice(), 0.01);
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
