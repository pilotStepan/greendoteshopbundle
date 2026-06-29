<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Entity\Project\Settings;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VoucherCalculationType as VouchCalc;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\SumDiscountStrategy;
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\HighestDiscountStrategy;
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\DiscountCombinationStrategyInterface;

/**
 * Tests for the three configuration axes that affect price calculation:
 *
 *  1. free_from_price_includes_vat  — whether the transport/payment free-from threshold
 *     is compared against the gross (VAT-inclusive) or net (VAT-exclusive) purchase total.
 *
 *  2. after_registration_discount   — a bonus discount percentage that acts as a fallback
 *     client discount when no explicit ClientDiscount is present, for the two
 *     …PlusAfterRegistrationDiscount DiscountCalculationType variants.
 *
 *  3. discountCombinationStrategy   — SumDiscountStrategy (product + client) vs
 *     HighestDiscountStrategy (max of product, client).
 *
 * IMPORTANT: PriceCalculationTestCase::setUp() permanently stubs settings to
 *   findParameterValueWithName → 20, free_from flag → 0, factory strategy → 'sum'.
 * PHPUnit does not allow re-stubbing an already-configured mock method, so the helpers
 * in this class build fresh mocks / pass values directly rather than mutating the shared ones.
 */
class PriceSettingsTest extends PriceCalculationTestCase
{
    // -------------------------------------------------------------------------
    // Local helpers
    // -------------------------------------------------------------------------

    /**
     * Build a fresh SettingsRepository mock with the requested flag and bonus values.
     * Used when the default stubs in setUp() would produce wrong results.
     *
     * @return SettingsRepository&MockObject
     */
    private function makeSettingsRepository(bool $freeFromIncludesVat, int $afterRegistration): SettingsRepository
    {
        $sr = $this->createMock(SettingsRepository::class);
        $sr->method('findOneBy')
            ->with(['name' => 'free_from_price_includes_vat'])
            ->willReturn(
                (new Settings())
                    ->setName('free_from_price_includes_vat')
                    ->setValue($freeFromIncludesVat ? 1 : 0),
            )
        ;
        $sr->method('findParameterValueWithName')->willReturn($afterRegistration);

        return $sr;
    }

    /**
     * Build a ProductVariantPriceFactory with the chosen discount combination strategy.
     * Mirrors the wiring in PriceCalculationTestCase::setUp() but with a different key.
     */
    private function createFactoryWithStrategy(string $key): ProductVariantPriceFactory
    {
        $locator = new ServiceLocator([
            'sum' => fn() => new SumDiscountStrategy(),
            'highest' => fn() => new HighestDiscountStrategy(),
        ]);

        return new ProductVariantPriceFactory(
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils,
            $this->settingsRepository,
            $this->productProductRepository,
            $locator,
            $key,
        );
    }

    /**
     * Construct a PurchasePrice with custom settings / factory, bypassing the shared stubs.
     */
    private function createPurchasePriceWithSettings(
        Purchase                    $purchase,
        VatCalc                     $vatCalc,
        DiscCalc                    $discCalc,
        Currency                    $currency,
        SettingsRepository          $settingsRepository,
        ?ProductVariantPriceFactory $factory = null,
    ): PurchasePrice
    {
        $conversionRate = $currency->getConversionRates()->first();

        return new PurchasePrice(
            $purchase,
            $vatCalc,
            $discCalc,
            $currency,
            $conversionRate,
            VouchCalc::WithoutVoucher,
            $factory ?? $this->productVariantPriceFactory,
            $this->priceUtils,
            $this->serviceCalculationUtils,
            $settingsRepository,
        );
    }

    /**
     * Construct a ProductVariantPrice with an explicit afterRegistrationBonus integer,
     * bypassing the fixed value (20) returned by the shared settingsRepository stub.
     */
    private function createProductVariantPriceWithBonus(
        ProductVariant|PurchaseProductVariant $variant,
        Currency                              $currency,
        VatCalc                               $vatCalc,
        DiscCalc                              $discCalc,
        ?int                                  $amount,
        int                                   $bonus,
        ?DiscountCombinationStrategyInterface $strategy = null,
    ): ProductVariantPrice
    {
        $conversionRate = $currency->getConversionRates()->first();

        return new ProductVariantPrice(
            $variant,
            $amount,
            $conversionRate,
            $vatCalc,
            $discCalc,
            $bonus,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils,
            $this->productProductRepository,
            $strategy ?? new SumDiscountStrategy(),
        );
    }

    // -------------------------------------------------------------------------
    // 1. free_from_price_includes_vat
    // -------------------------------------------------------------------------

    /**
     * Scenario: one product variant, unit price 1 000, VAT 21 %, amount 1.
     * Transportation: price 100, VAT 0 %, freeFromPrice 1 100.
     *
     *   Net total   = 1 000  →  1 000 < 1 100  → transport charged  (flag = false)
     *   Gross total = 1 210  →  1 210 ≥ 1 100  → transport free     (flag = true)
     */
    #[DataProviderExternal(PriceSettingsDataProvider::class, 'freeFromPriceIncludesVat')]
    public function testFreeFromPriceIncludesVat(
        bool   $flag,
        float  $expectedTotalWithServices,
        ?float $expectedTransportationPrice,
    ): void
    {
        // ARRANGE
        $sr = $this->makeSettingsRepository(freeFromIncludesVat: $flag, afterRegistration: 0);

        $price = FactoryUtil::makePrice(unitPrice: 1000.0, vatPercentage: 21.0);
        $transportation = FactoryUtil::makeTransportation(price: 100.0, vatPercentage: 0.0, freeFromPrice: 1100.0);

        $purchase = $this->createPurchase(
            ppv: [['amount' => 1, 'prices' => [['price' => $price, 'discounted' => null]]]],
            clientDiscount: null,
            vouchers: null,
        );
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getPaymentType')->willReturn(null);

        $this->handlingPriceRepository
            ->method('getByDate')
            ->willReturnCallback(fn($entity) => $entity->getHandlingPrices()->first())
        ;

        // ACT
        $pp = $this->createPurchasePriceWithSettings(
            $purchase,
            VatCalc::WithoutVAT,
            DiscCalc::WithDiscount,
            FactoryUtil::czk(),
            $sr,
        );

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedTotalWithServices,
            $pp->getPrice(includeServices: true),
            0.001,
            'Total price including services',
        );
        $this->assertEquals(
            $expectedTransportationPrice,
            $pp->getTransportationPrice(),
            'Transportation price (null when free)',
        );
    }

    // -------------------------------------------------------------------------
    // 2a. after_registration_discount  — WithDiscountPlusAfterRegistrationDiscount
    // -------------------------------------------------------------------------

    /**
     * Scenario: unit price 1 000, VAT 21 %, no product discount, no parent-product discount,
     * DiscCalc::WithDiscountPlusAfterRegistrationDiscount, VatCalc::WithoutVAT, CZK.
     *
     * When clientDiscount is null the afterRegistrationBonus is used as the fallback.
     * When clientDiscount is present it overrides the bonus completely.
     *
     * Formula (SumStrategy, no product discount):
     *   effectiveClient = clientDiscount ?? bonus
     *   totalDisc       = combine(0, effectiveClient) = effectiveClient
     *   price           = 1 000 × (1 − totalDisc/100)
     */
    #[DataProviderExternal(PriceSettingsDataProvider::class, 'afterRegistrationDiscount')]
    public function testAfterRegistrationDiscountFallback(
        int    $bonus,
        ?float $clientDiscount,
        float  $expectedPrice,
    ): void
    {
        // ARRANGE
        if ($clientDiscount !== null) {
            $this->setupClientDiscountForProductVariant($clientDiscount);
        }

        $price = FactoryUtil::makePrice(unitPrice: 1000.0, vatPercentage: 21.0, discount: 0.0);
        $variant = new ProductVariant();
        $this->priceRepository
            ->method('findPricesByDateAndProductVariantNew')
            ->willReturn([['price' => $price, 'discounted' => null]])
        ;

        // ACT
        $pvp = $this->createProductVariantPriceWithBonus(
            $variant,
            FactoryUtil::czk(),
            VatCalc::WithoutVAT,
            DiscCalc::WithDiscountPlusAfterRegistrationDiscount,
            amount: 1,
            bonus: $bonus,
        );

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedPrice,
            $pvp->getPrice(),
            0.001,
            "Price with after-registration bonus={$bonus}, clientDiscount=" . ($clientDiscount ?? 'null'),
        );
    }

    // -------------------------------------------------------------------------
    // 2b. after_registration_discount — WithoutDiscountPlusAfterRegistrationDiscount
    // -------------------------------------------------------------------------

    /**
     * Scenario: same product (unit price 1 000, product discount 10 %),
     * DiscCalc::WithoutDiscountPlusAfterRegistrationDiscount, VatCalc::WithoutVAT, CZK.
     *
     * This variant returns (clientDiscount ?? bonus) and IGNORES the product discount
     * and the combination strategy entirely:
     *   price = 1 000 × (1 − (clientDiscount ?? bonus) / 100)
     */
    #[DataProviderExternal(PriceSettingsDataProvider::class, 'withoutDiscountPlusRegistration')]
    public function testWithoutDiscountPlusAfterRegistrationDiscount(
        int    $bonus,
        ?float $clientDiscount,
        float  $expectedPrice,
    ): void
    {
        // ARRANGE
        if ($clientDiscount !== null) {
            $this->setupClientDiscountForProductVariant($clientDiscount);
        }

        // Product discount 10 % wired via 'discounted' key — must NOT affect this DiscCalc type.
        $discountedPrice = FactoryUtil::makePrice(unitPrice: 1000.0, vatPercentage: 21.0, discount: 10.0);
        $variant = new ProductVariant();
        $this->priceRepository
            ->method('findPricesByDateAndProductVariantNew')
            ->willReturn([['discounted' => $discountedPrice]])
        ;

        // ACT
        $pvp = $this->createProductVariantPriceWithBonus(
            $variant,
            FactoryUtil::czk(),
            VatCalc::WithoutVAT,
            DiscCalc::WithoutDiscountPlusAfterRegistrationDiscount,
            amount: 1,
            bonus: $bonus,
        );

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedPrice,
            $pvp->getPrice(),
            0.001,
            "Price with WithoutDiscountPlusAfterRegistration, bonus={$bonus}, clientDiscount=" . ($clientDiscount ?? 'null'),
        );
    }

    // -------------------------------------------------------------------------
    // 3a. discountCombinationStrategy — ProductVariantPrice level
    // -------------------------------------------------------------------------

    /**
     * Scenario: unit price 1 000, VatCalc::WithoutVAT, DiscCalc::WithDiscount, CZK.
     * productDiscount and clientDiscount vary per case.
     *
     * SumDiscountStrategy:     totalDisc = productDisc + clientDisc
     * HighestDiscountStrategy: totalDisc = max(productDisc, clientDisc)
     *   price = 1 000 × (1 − totalDisc/100)
     *
     * Asserts both getDiscountPercentage() and the final getPrice().
     */
    #[DataProviderExternal(PriceSettingsDataProvider::class, 'discountCombinationStrategy')]
    public function testDiscountCombinationStrategyProductVariant(
        DiscountCombinationStrategyInterface $strategy,
        float                                $productDiscount,
        float                                $clientDiscount,
        float                                $expectedDiscountPct,
        float                                $expectedPrice,
    ): void
    {
        // ARRANGE
        $this->setupClientDiscountForProductVariant($clientDiscount);

        // Product discount wired via 'discounted' key (the path that sets discountPercentage).
        $discountedPrice = FactoryUtil::makePrice(unitPrice: 1000.0, vatPercentage: 21.0, discount: $productDiscount);
        $variant = new ProductVariant();
        $this->priceRepository
            ->method('findPricesByDateAndProductVariantNew')
            ->willReturn([['discounted' => $discountedPrice]])
        ;

        // ACT
        $pvp = $this->createProductVariantPrice(
            $variant,
            FactoryUtil::czk(),
            VatCalc::WithoutVAT,
            DiscCalc::WithDiscount,
            amount: 1,
            combinationStrategy: $strategy,
        );

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedDiscountPct,
            $pvp->getDiscountPercentage(),
            0.001,
            "Effective discount percentage mismatch",
        );
        $this->assertEqualsWithDelta(
            $expectedPrice,
            $pvp->getPrice(),
            0.001,
            "Final price mismatch",
        );
    }

    // -------------------------------------------------------------------------
    // 3b. discountCombinationStrategy — PurchasePrice level (integration)
    // -------------------------------------------------------------------------

    /**
     * Same discount scenario but routed through PurchasePrice with a custom factory.
     * Verifies that the strategy chosen at factory construction propagates through
     * ProductVariantPriceFactory → ProductVariantPrice → PurchasePrice::getPrice().
     *
     * Setup: one PurchaseProductVariant, unit price 1 000, product discount 10 %,
     * clientDiscount wired on Purchase mock, VatCalc::WithoutVAT, DiscCalc::WithDiscount, CZK.
     */
    #[DataProviderExternal(PriceSettingsDataProvider::class, 'discountCombinationPurchase')]
    public function testDiscountCombinationStrategyPurchase(
        string $strategyKey,
        float  $clientDiscount,
        float  $expectedPrice,
    ): void
    {
        // ARRANGE
        $factory = $this->createFactoryWithStrategy($strategyKey);

        // Product discount 10 % wired via 'discounted' key so it flows into discountPercentage.
        $discountedPrice = FactoryUtil::makePrice(unitPrice: 1000.0, vatPercentage: 21.0, discount: 10.0);
        $purchase = $this->createPurchase(
            ppv: [['amount' => 1, 'prices' => [['discounted' => $discountedPrice]]]],
            clientDiscount: $clientDiscount,
            vouchers: null,
        );
        $purchase->method('getTransportation')->willReturn(null);
        $purchase->method('getPaymentType')->willReturn(null);

        // ACT
        $pp = $this->createPurchasePriceWithSettings(
            $purchase,
            VatCalc::WithoutVAT,
            DiscCalc::WithDiscount,
            FactoryUtil::czk(),
            $this->settingsRepository,
            $factory,
        );

        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedPrice,
            $pp->getPrice(),
            0.001,
            "Purchase price with strategy={$strategyKey}, clientDiscount={$clientDiscount}",
        );
    }
}
