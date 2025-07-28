<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;


class ProductVariantPriceDataProvider
{
    public static function withVat(): array
    {
        return [
            /* W01 ─ simple happy-path 3 × 100 @ 21 % */
            'W01' => [
                'productType' => 'pv',
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(100, 21),
                    ],
                ],
                'amount' => 3,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 363.0,
            ],

            /* W02 ─ rounding edge-case 2 × 49.91 @ 15 % */
            'W02-round' => [
                'productType' => 'pv',
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(49.91, 15),
                    ],
                ],
                'amount' => 2,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 114.8,  // 99.82 × 1.15 -> 114.793 -> round(1)
            ],

            /* W03 ─ tier / package price 10 × 90 @ 20 % (package size 5) */
            'W03-tier' => [
                'productType' => 'pv',
                'prices' => [[
                    'price' => FactoryUtil::makePrice(unitPrice: 90, vatPercentage: 20, minimalAmount: 5, discount: 0, minPrice: 90, isPackage: true),
                ]],
                'amount' => 10,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 1080.0, // 900 + 20 % VAT
            ],

            /* W04 ─ CZK->EUR conversion 1 × 100 @ 21 % */
            'W04-eur' => [
                'productType' => 'pv',
                'prices' => [[
                    'price' => FactoryUtil::makePrice(100, 21),
                ]],
                'amount' => 1,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::eur(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 4.84,   // 121 × 0.04 -> 4.84 (EUR rounding 2 dec.)
            ],
        ];
    }

    public static function withoutVat(): array
    {
        return [
            'WO1' => [
                'productType' => 'pv',
                'prices' => [[
                    'price' => FactoryUtil::makePrice(100, 21),
                ]],
                'amount' => 3,
                'vatCalc' => VatCalc::WithoutVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 300.0,
            ],
            'WO2-eur' => [
                'productType' => 'pv',
                'prices' => [[
                    'price' => FactoryUtil::makePrice(100, 21),
                ]],
                'amount' => 1,
                'vatCalc' => VatCalc::WithoutVAT,
                'currency' => FactoryUtil::eur(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 4.0,   // 100 × 0.04
            ],
        ];
    }

    public static function onlyVat(): array
    {
        return [
            'OV1' => [
                'productType' => 'pv',
                'prices' => [[
                    'price' => FactoryUtil::makePrice(100, 21),
                ]],
                'amount' => 3,
                'vatCalc' => VatCalc::OnlyVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 63.0,
            ],
            'OV2-eur' => [
                'productType' => 'pv',
                'prices' => [[
                    'price' => FactoryUtil::makePrice(100, 21),
                ]],
                'amount' => 1,
                'vatCalc' => VatCalc::OnlyVAT,
                'currency' => FactoryUtil::eur(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 0.84,  // 21 × 0.04
            ],
        ];
    }

    public static function onlyProductDiscount(): array
    {
        return [
            'DP1' => [
                'productType' => 'pv',
                'prices' => [
                    1 => [
                        'price' => FactoryUtil::makePrice(100, 20, discount: 0),
                        'discounted' => FactoryUtil::makePrice(100, 20, discount: 10),
                    ]],
                'amount' => 3,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::OnlyProductDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 324.0, //((100 * 3) + 20%) -10% = 324
            ],
        ];
    }

    public static function discountEdge(): array
    {
        return [
            'DE1' => [
                'productType' => 'pv',
                'prices' => [
                    1 => [
                        'price' => FactoryUtil::makePrice(1000, 21, discount: 0),
                        'discounted' => FactoryUtil::makePrice(1000, 21, discount: 100),
                    ]],
                'amount' => 3,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 0.0, //((100 * 3) + 20%) -10% = 324
            ],
            'DE2' => [
                'productType' => 'pv',
                'prices' => [
                    1 => [
                        'price' => FactoryUtil::makePrice(1000, 21, discount: 0),
                        'discounted' => FactoryUtil::makePrice(1000, 21, discount: 90),
                    ]],
                'amount' => 3,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithDiscount,
                'clientDiscount' => 10,
                'expectedPrice' => 0.0, //((100 * 3) + 20%) -10% = 324
            ],
        ];
    }

    public static function clientAndProduct(): array
    {
        return [
            'DC1' => [
                'productType' => 'pv',
                'prices' => [
                    1 => [
                        'price' => FactoryUtil::makePrice(100, 20, discount: 0),
                        'discounted' => FactoryUtil::makePrice(100, 20, discount: 10),
                    ],
                ],
                'amount' => 2,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithDiscount,
                'clientDiscount' => 5,
                'expectedPrice' => 204, // 2x100 + 20% (vat) = 240 - (10% +5%) = 204
            ],
        ];
    }

    public static function afterRegistration(): array
    {
        return [
            'DR1' => [
                'productType' => 'pv',
                'prices' => [[
                    'discounted' => FactoryUtil::makePrice(100, 20, discount: 10),
                ]],
                'amount' => 2,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithDiscountPlusAfterRegistrationDiscount,
                'clientDiscount' => null,      // null user ⇒ + 20 % bonus
                'expectedPrice' => 168,     // 2x100 + 20% (vat) - (10% (product discount) + 20% (new client bonus) )
            ],
        ];
    }

    public static function minPrice(): array
    {
        return [
            'MP1' => [
                'productType' => 'pv',
                'prices' => [[
                    'price' => FactoryUtil::makePrice(50, 0, minPrice: 60),
                ]],
                'amount' => 1,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 60.0,
            ],
        ];
    }

    public static function currencySetter(): array
    {
        return [
            'CS1' => [
                'productType' => 'pv',
                'prices' => [[
                    'price' => FactoryUtil::makePrice(100, 21),
                ]],
                'amount' => 1,
                'vatCalc' => VatCalc::WithVAT,
                'currencyCZK' => FactoryUtil::czk(),   // initial
                'currencyEUR' => FactoryUtil::eur(),   // later via setCurrency()
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPriceCZK' => 121.0,
                'expectedPriceEUR' => 4.84,
            ],
        ];
    }

    public static function tierPrice(): array
    {
        return [
            'TP1' => [
                'productType' => 'pv',
                'prices' => [
                    ['price' => FactoryUtil::makePrice(90, 0, minimalAmount: 10, isPackage: true)],
                    ['price' => FactoryUtil::makePrice(100, 0, minimalAmount: 1)],
                ],
                'amount' => 17,
                'vatCalc' => VatCalc::WithoutVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectedPrice' => 1600.0,
            ],
        ];
    }

    public static function ppvCustomPrice(): array
    {
        $czk = FactoryUtil::czk();
        $czk2 = FactoryUtil::czkThreeSpaceRounding();
        $eur = FactoryUtil::eur();

        return [
            'with_vat_10pct_discount' => [
                'price' => FactoryUtil::makePrice(100.0, 21.0, 1, 10.0),
                'amount' => 1,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => $czk,
                'discCalc' => DiscCalc::WithDiscount,
                'expectedPrice' => 108.9,
            ],
            'with_vat_no_discount' => [
                'price' => FactoryUtil::makePrice(100.0, 21.0, 1, 10.0),
                'amount' => 1,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => $czk,
                'discCalc' => DiscCalc::WithoutDiscount,
                'expectedPrice' => 121.0,
            ],
            'zero_vat' => [
                'price' => FactoryUtil::makePrice(99.99, 0.0),
                'amount' => 1,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => $czk2,
                'discCalc' => DiscCalc::WithoutDiscount,
                'expectedPrice' => 99.99,
            ],
            'foreign_currency' => [
                'price' => FactoryUtil::makePrice(10.0, 21.0),
                'amount' => 10,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => $eur,
                'discCalc' => DiscCalc::WithoutDiscount,
                'expectedPrice' => 10 * 10 * 1.21 * $eur->getConversionRate(),
            ],
            'free_item' => [
                'price' => FactoryUtil::makePrice(0.0, 0.0),
                'amount' => 500,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => $czk,
                'discCalc' => DiscCalc::WithoutDiscount,
                'expectedPrice' => 0.0,
            ],
            'vat_100_percent' => [
                'price' => FactoryUtil::makePrice(50.0, 100.0),
                'amount' => 2,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => $czk,
                'discCalc' => DiscCalc::WithoutDiscount,
                'expectedPrice' => 200.0,
            ],
            'large_quantity' => [
                'price' => FactoryUtil::makePrice(1.99, 0.0),
                'amount' => 10_000,
                'vatCalc' => VatCalc::WithoutVAT,
                'currency' => $czk,
                'discCalc' => DiscCalc::WithoutDiscount,
                'expectedPrice' => 19_900.0,
            ],
            'discount_100_percent' => [
                'price' => FactoryUtil::makePrice(80.0, 21.0, 1, 100.0),
                'amount' => 1,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => $czk,
                'discCalc' => DiscCalc::WithDiscount,
                'expectedPrice' => 0.0,
            ],
        ];
    }

    public static function ppvCustomPriceInvalidCases(): array
    {
        return [
            'negative_unit_price' => [
                'price' => FactoryUtil::makePrice(-5.0, 21.0),
                'amount' => 1,
                'vatCalc' => VatCalc::WithVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
            ],
            'zero_amount' => [
                'price' => FactoryUtil::makePrice(10.0, 21.0),
                'amount' => 0,
                'vatCalc' => VatCalc::WithVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
            ],
            'discount_over_100_percent' => [
                'price' => FactoryUtil::makePrice(10.0, 21.0, 1, 150.0),
                'amount' => 1,
                'vatCalc' => VatCalc::WithVAT,
                'discCalc' => DiscCalc::WithDiscount,
            ],
        ];
    }
}