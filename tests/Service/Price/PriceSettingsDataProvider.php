<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\SumDiscountStrategy;
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\HighestDiscountStrategy;

/**
 * Data providers for PriceSettingsTest.
 *
 * All expected values are derived from the formulas in ProductVariantPrice:
 *   base  = unitPrice * amount
 *   disc  = base * discountPct / 100
 *   net   = base - disc  (floored at minPrice)
 *   gross = net  * (1 + vatPct/100)  (WithVAT)
 *   out   = round(net|gross * conversionRate, currency.rounding)
 *
 * Discount combination:
 *   SumStrategy:     productDisc + clientDisc
 *   HighestStrategy: max(productDisc, clientDisc)
 *
 * Service free-from check (ServiceCalculationUtils::isFreeHandling):
 *   free when theoreticalAmount >= freeFromPrice
 *   theoreticalAmount = purchase-product total at rate 1,
 *     WithVAT  when free_from_price_includes_vat = true
 *     WithoutVAT otherwise
 */
class PriceSettingsDataProvider
{
    /**
     * Cases for testFreeFromPriceIncludesVat.
     *
     * Fixed setup (built in test): unit price 1000, VAT 21 %, amount 1,
     * transportation price 100 (VAT 0 %), freeFromPrice 1 100.
     * Purchase VAT type: WithoutVAT, currency: CZK (rate 1, rounding 1).
     *
     * Net total   = 1 000   →  1 000 < 1 100  → transport NOT free when flag=false
     * Gross total = 1 210   →  1 210 ≥ 1 100  → transport IS  free when flag=true
     *
     * @return array<string, array{flag: bool, expectedTotalWithServices: float, expectedTransportationPrice: ?float}>
     */
    public static function freeFromPriceIncludesVat(): array
    {
        return [
            'FFV01: flag false → net used, below threshold → transport charged' => [
                'flag' => false,
                'expectedTotalWithServices' => 1100.0, // 1000 + 100
                'expectedTransportationPrice' => 100.0,
            ],
            'FFV02: flag true → gross used, above threshold → transport free' => [
                'flag' => true,
                'expectedTotalWithServices' => 1000.0, // 1000 + 0
                'expectedTransportationPrice' => null,  // PurchasePrice::getTransportationPrice() returns null when 0
            ],
        ];
    }

    /**
     * Cases for testAfterRegistrationDiscountFallback.
     *
     * Fixed setup: unit price 1 000, VAT 21 %, no product discount,
     * DiscCalc::WithDiscountPlusAfterRegistrationDiscount, VatCalc::WithoutVAT, CZK.
     *
     * Formula (SumStrategy, no product disc):
     *   effectiveClient = clientDiscount ?? bonus
     *   totalDisc = combine(0, effectiveClient) = effectiveClient
     *   price = 1 000 * (1 - totalDisc/100)
     *
     * @return array<string, array{bonus: int, clientDiscount: ?float, expectedPrice: float}>
     */
    public static function afterRegistrationDiscount(): array
    {
        return [
            'AR01: bonus 0, no client → no discount applied' => [
                'bonus' => 0,
                'clientDiscount' => null,
                'expectedPrice' => 1000.0,
            ],
            'AR02: bonus 10, no client → 10 % off' => [
                'bonus' => 10,
                'clientDiscount' => null,
                'expectedPrice' => 900.0,  // 1000 * 0.90
            ],
            'AR03: bonus 20, no client → 20 % off' => [
                'bonus' => 20,
                'clientDiscount' => null,
                'expectedPrice' => 800.0,  // 1000 * 0.80
            ],
            'AR04: bonus 20, client 15 % → client overrides bonus → 15 % off' => [
                'bonus' => 20,
                'clientDiscount' => 15.0,
                'expectedPrice' => 850.0,  // 1000 * 0.85; bonus not used when clientDiscount is set
            ],
        ];
    }

    /**
     * Cases for testWithoutDiscountPlusAfterRegistrationDiscount.
     *
     * Fixed setup: unit price 1 000, VAT 21 %, product discount 10 %,
     * DiscCalc::WithoutDiscountPlusAfterRegistrationDiscount, VatCalc::WithoutVAT, CZK.
     *
     * This DiscCalc case returns (clientDiscount ?? bonus) and completely ignores product discount.
     *   price = 1 000 * (1 - (clientDiscount ?? bonus) / 100)
     *
     * @return array<string, array{bonus: int, clientDiscount: ?float, expectedPrice: float}>
     */
    public static function withoutDiscountPlusRegistration(): array
    {
        return [
            'WD01: bonus 0, no client → no discount (product disc ignored)' => [
                'bonus' => 0,
                'clientDiscount' => null,
                'expectedPrice' => 1000.0,
            ],
            'WD02: bonus 10, no client → 10 % off (product 10 % ignored)' => [
                'bonus' => 10,
                'clientDiscount' => null,
                'expectedPrice' => 900.0,  // 1000 * 0.90
            ],
            'WD03: bonus 20, client 15 % → client wins → 15 % off (product 10 % ignored)' => [
                'bonus' => 20,
                'clientDiscount' => 15.0,
                'expectedPrice' => 850.0,  // 1000 * 0.85
            ],
        ];
    }

    /**
     * Cases for testDiscountCombinationStrategyProductVariant.
     *
     * Fixed setup: unit price 1 000, VAT 21 %, VatCalc::WithoutVAT, DiscCalc::WithDiscount, CZK.
     * productDiscount and clientDiscount vary per case.
     *
     * SumDiscountStrategy:     totalDisc = productDisc + clientDisc
     * HighestDiscountStrategy: totalDisc = max(productDisc, clientDisc)
     *   price = 1 000 * (1 - totalDisc/100)
     *
     * @return array<string, array{
     *     strategy: \Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\DiscountCombinationStrategyInterface,
     *     productDiscount: float,
     *     clientDiscount: float,
     *     expectedDiscountPct: float,
     *     expectedPrice: float,
     * }>
     */
    public static function discountCombinationStrategy(): array
    {
        return [
            'CS01: Sum  10%+15% = 25 % off → 750' => [
                'strategy' => new SumDiscountStrategy(),
                'productDiscount' => 10.0,
                'clientDiscount' => 15.0,
                'expectedDiscountPct' => 25.0,
                'expectedPrice' => 750.0,
            ],
            'CS02: Highest max(10,15) = 15 % off → 850' => [
                'strategy' => new HighestDiscountStrategy(),
                'productDiscount' => 10.0,
                'clientDiscount' => 15.0,
                'expectedDiscountPct' => 15.0,
                'expectedPrice' => 850.0,
            ],
            'CS03: Sum  10%+10% = 20 % off → 800' => [
                'strategy' => new SumDiscountStrategy(),
                'productDiscount' => 10.0,
                'clientDiscount' => 10.0,
                'expectedDiscountPct' => 20.0,
                'expectedPrice' => 800.0,
            ],
            'CS04: Highest max(10,10) = 10 % off → 900' => [
                'strategy' => new HighestDiscountStrategy(),
                'productDiscount' => 10.0,
                'clientDiscount' => 10.0,
                'expectedDiscountPct' => 10.0,
                'expectedPrice' => 900.0,
            ],
            'CS05: Highest max(20,5) = 20 % off → product beats client → 800' => [
                'strategy' => new HighestDiscountStrategy(),
                'productDiscount' => 20.0,
                'clientDiscount' => 5.0,
                'expectedDiscountPct' => 20.0,
                'expectedPrice' => 800.0,
            ],
            'CS06: Sum  20%+5% = 25 % off → 750' => [
                'strategy' => new SumDiscountStrategy(),
                'productDiscount' => 20.0,
                'clientDiscount' => 5.0,
                'expectedDiscountPct' => 25.0,
                'expectedPrice' => 750.0,
            ],
        ];
    }

    /**
     * Cases for testDiscountCombinationStrategyPurchase.
     *
     * Fixed setup: PurchaseProductVariant with unit price 1 000, VAT 21 %, product discount 10 %,
     * VatCalc::WithoutVAT, DiscCalc::WithDiscount, CZK.
     * clientDiscount wired on the Purchase mock (via createPurchase).
     *
     * @return array<string, array{strategyKey: string, clientDiscount: float, expectedPrice: float}>
     */
    public static function discountCombinationPurchase(): array
    {
        return [
            'CP01: Sum  strategy → 10%+15% = 25 % off → 750' => [
                'strategyKey' => 'sum',
                'clientDiscount' => 15.0,
                'expectedPrice' => 750.0,
            ],
            'CP02: Highest strategy → max(10,15) = 15 % off → 850' => [
                'strategyKey' => 'highest',
                'clientDiscount' => 15.0,
                'expectedPrice' => 850.0,
            ],
        ];
    }
}
