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
                    ]
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
                    ]
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
                    'price' => FactoryUtil::makePrice(
                        unitPrice: 90,
                        vatPercentage: 20,
                        minimalAmount: 5,
                        discount: 0,
                        minPrice: 90,
                        isPackage: true,
                    ),
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
            /* FIXME: discountValue pocita se na zaklade amount
                * amount:1 & discount:10 = discountValue:10
                * amount:3 & discount:10 = discountValue:30
             */
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
                'expectedDiscountValue' => 30.0,
                'expectedPrice' => 216.0, // (200 –10 %) +20 %
            ],
        ];
    }

    public static function clientAndProduct(): array
    {
        return [
            // FIXME: FAILS
            'DC1' => [
                'productType' => 'pv',
                'prices' => [
                    1 => [
                        'price' => FactoryUtil::makePrice(100, 20, discount: 0),
                        'discounted' => FactoryUtil::makePrice(100, 20, discount: 10),
                    ]
                ],
                'amount' => 2,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithDiscount,
                'clientDiscount' => 5,
                'expectedDiscountValue' => 15,
                'expectedPrice' => 204.0, // 200 –15 % +20 %
            ],
        ];
    }

    public static function afterRegistration(): array
    {
        return [
            // FIXME: FAILS
            'DR1' => [
                'productType' => 'pv',
                'prices' => [[
                    'price' => FactoryUtil::makePrice(100, 20, discount: 10),
                ]],
                'amount' => 2,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithDiscountPlusAfterRegistrationDiscount,
                'clientDiscount' => null,      // null user ⇒ + 10 % bonus
                'expectedDiscountValue' => 20,
                'expectedPrice' => 192.0,     // 200 – 20 % + 20 %
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
            // FIXME: FAILS
            'TP1' => [
                'productType' => 'pv',
                'prices' => [
                    ['price' => FactoryUtil::makePrice(100, 0, minimalAmount: 1)],
                    ['price' => FactoryUtil::makePrice(90, 0, minimalAmount: 10, isPackage: true)],
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

    public static function mixedVatException(): array
    {
        return [
            // FIXME: FAILS
            'EX1' => [
                'productType' => 'pv',
                'prices' => [
                    ['price' => FactoryUtil::makePrice(100, 21)],
                    ['price' => FactoryUtil::makePrice(90, 15)],
                ],
                'amount' => 5,
                'vatCalc' => VatCalc::WithVAT,
                'currency' => FactoryUtil::czk(),
                'discCalc' => DiscCalc::WithoutDiscount,
                'clientDiscount' => 0,
                'expectExceptionMsg' => 'Vat is different for prices on same Project/ProductVariant',
            ],
        ];
    }
}