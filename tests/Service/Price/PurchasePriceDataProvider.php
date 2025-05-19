<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;


class PurchasePriceDataProvider
{
    public static function basicTotals(): array
    {
        $ppVariants = [
            [
                'amount' => 1,
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(50, 21),
                        'discounted' => null,
                    ],
                ]
            ],
            [
                'amount' => 1,
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(50, 21),
                        'discounted' => null,
                    ],
                ],
            ],
        ];

        $ppVariantsBig = [
            [  // s dph: 13,747.704555 ~ 13,747.7
                'amount' => 13,
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(919.5789, 15),
                        'discounted' => null,
                    ],
                ],
            ],
            [ // s dph: 770.1918 ~ 770.2
                'amount' => 51,
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(13.132, 15),
                        'discounted' => null,
                    ],
                ],
            ],
            [ // s dph: 11,868.42895 ~ 11,868.4
                'amount' => 37,
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(278.929, 15),
                        'discounted' => null,
                    ],
                ],
            ],
        ];

        return [
            'B01: With VAT, purchase price with VAT' => [
                'ppv' => $ppVariants,
                'vatCalc' => VatCalc::WithVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
                'currency' => FactoryUtil::czk(),
                'expectedPurchasePrice' => 121.0,
            ],
            'B02: Without VAT, purchase price without VAT' => [
                'ppv' => $ppVariants,
                'vatCalc' => VatCalc::WithoutVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
                'currency' => FactoryUtil::czk(),
                'expectedPurchasePrice' => 100.0,
            ],
            'B03: Only VAT, purchase price VAT slice only' => [
                'ppv' => $ppVariants,
                'vatCalc' => VatCalc::OnlyVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
                'currency' => FactoryUtil::czk(),
                'expectedPurchasePrice' => 21.0,
            ],
            'B04: With VAT, purchase price with VAT, big' => [
                'ppv' => $ppVariantsBig,
                'vatCalc' => VatCalc::WithVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
                'currency' => FactoryUtil::czk(),
                'expectedPurchasePrice' => 26386.3, // 26,386.325305 -> round(1) -> 26,386.3
            ],
            'B05: Without VAT, purchase price without VAT, big' => [
                'ppv' => $ppVariantsBig,
                'vatCalc' => VatCalc::WithoutVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
                'currency' => FactoryUtil::czk(),
                'expectedPurchasePrice' => 22944.6, // 26,386.325305 / 1.15 = 22,944.6307 -> round(1) -> 22,944.6
            ],
            'B06: Only VAT, purchase price VAT slice only, big' => [
                'ppv' => $ppVariantsBig,
                'vatCalc' => VatCalc::OnlyVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
                'currency' => FactoryUtil::czk(),
                'expectedPurchasePrice' => 3441.7, // 26,386.325305 - 22,944.6307 = 3,441.694605 -> round(1) -> 3,441.7 // FIXME: Actual: 3441.8
            ],
            'B07: EUR, With VAT, purchase price with VAT, big' => [
                'ppv' => $ppVariantsBig,
                'vatCalc' => VatCalc::WithVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
                'currency' => FactoryUtil::eur(),
                'expectedPurchasePrice' => 1055.45, // 26,386.325305 * 0.04 = 1,055.4530122 -> round(2) -> 1,055.45
            ],
            'B08: EUR, Without VAT, purchase price without VAT, big' => [
                'ppv' => $ppVariantsBig,
                'vatCalc' => VatCalc::WithoutVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
                'currency' => FactoryUtil::eur(),
                'expectedPurchasePrice' => 917.79, // 22,944.6307 * 0.04 = 917.785228 -> round(2) -> 917.79
            ],
            'B09: EUR, Only VAT, purchase price VAT slice only, big' => [
                'ppv' => $ppVariantsBig,
                'vatCalc' => VatCalc::OnlyVAT,
                'discCalc' => DiscCalc::WithoutDiscount,
                'currency' => FactoryUtil::eur(),
                'expectedPurchasePrice' => 137.67, // 3,441.694605 * 0.04 = 137.6677842 -> round(2) -> 137.67
            ],
        ];
    }

    public static function servicePrices(): array
    {
        $baseConfig = [
            'vatCalc' => VatCalc::WithVAT,
            'discCalc' => DiscCalc::WithoutDiscount,
            'currency' => FactoryUtil::czk(),
        ];
        $s01Ppv = [
            [
                'amount' => 1,
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(80, 21),
                        'discounted' => null,
                    ],
                ],
            ],
        ];
        $s03Ppv = [
            [
                'amount' => 3,
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(170.6345, 15),
                        'discounted' => null,
                    ],
                ],
            ],
        ];
        return [
            'S01: Transport free, payment paid' => [
                'ppv' => $s01Ppv,
                ...$baseConfig,
                'transportation' => FactoryUtil::makeTransportation(99, 21, 300),
                'paymentType' => FactoryUtil::makePaymentType(30, 21, 500),
                'expectedPurchasePrice' => 252.9, // (80 + 99 + 30) * 1.21 = 252.89 -> round(1) -> 252.9
                'expectedTransportationPrice' => 119.8, // 99 * 1.21 = 119.79 -> round(1) -> 119.8
                'expectedPaymentPrice' => 36.3, // 30 * 1.21 = 36.3
            ],
            'S02: Payment paid, transport free' => [
                'ppv' => $s01Ppv,
                ...$baseConfig,
                'transportation' => FactoryUtil::makeTransportation(99, 21, 79.9),
                'paymentType' => FactoryUtil::makePaymentType(30, 21, 80.1), // TODO: free from price pocita se bez DPH (?)
                'expectedPurchasePrice' => 133.1, // (80 + 0 + 30) * 1.21 = 133.1
                'expectedTransportationPrice' => 0.0,
                'expectedPaymentPrice' => 36.3,
            ],
            'S03-EUR: Currency conversion and rounding' => [
                'ppv' => $s03Ppv,
                ...$baseConfig,
                'currency' => FactoryUtil::eur(),
                'transportation' => FactoryUtil::makeTransportation(99, 15, 511.9036), // free from price
                'paymentType' => null,
                'expectedPurchasePrice' => 28.10, // (170.6345 * 3 + transportationPrice) * 0.04 * 1.15 = 28.1015... -> round(2) -> 28.10
                'expectedTransportationPrice' => 4.55, // 99 * 0.04 * 1.15 = 5.554 -> round(2) -> 4.55
                'expectedPaymentPrice' => 0.0,
            ],
        ];
    }

    public static function discounts(): array
    {
        $baseConfig = [
            'vatCalc' => VatCalc::WithVAT,
            'discCalc' => DiscCalc::WithDiscount,
            'currency' => FactoryUtil::czk(),
            'clientDiscount' => 0,
        ];

        return [
            'D01: No product discount at all' => [
                'ppv' => [
                    [
                        'amount' => 10,
                        'prices' => [
                            1 => [
                                'price' => FactoryUtil::makePrice(100, 21, 1, discount: 0),
                                'discounted' => null,
                            ]
                        ]
                    ],
                ],
                ...$baseConfig,
                'expectedPurchasePrice' => 1210.0 // 100 * 10 * 1.21 = 1,210.0
            ],
            'D02: Only a discounted price exists (no base price)' => [
                'ppv' => [
                    [
                        'amount' => 11,
                        'prices' => [
                            1 => [
                                'price' => null,
                                'discounted' => FactoryUtil::makePrice(100, 21, 1, discount: 10),
                            ]
                        ]
                    ],
                ],
                ...$baseConfig,
                'expectedPurchasePrice' => 1197.9, // 100 * 11 * 0.90 * 1.21 = 1,197.9
            ],
            'D03: Both normal and discounted rows for the same minimalAmount' => [
                'ppv' => [
                    [
                        'amount' => 9,
                        'prices' => [
                            1 => [
                                'price' => FactoryUtil::makePrice(100, 21, 1, discount: 0),
                                'discounted' => FactoryUtil::makePrice(100, 21, 1, discount: 10)
                            ]
                        ]
                    ],
                ],
                ...$baseConfig,
                'expectedPurchasePrice' => 980.1, // 100 * 9 * 1.21 * 0.90 = 980.1
            ],
            'D04: Two tiers, discount only on the second tier' => [
                'ppv' => [
                    [
                        'amount' => 12,
                        'prices' => [
                            10 => [
                                'discounted' => FactoryUtil::makePrice(99.9, 21, 10, discount: 20, isPackage: true)
                            ],
                            1 => [
                                'price' => FactoryUtil::makePrice(121.99, 21, 1, discount: 0),
                            ]
                        ]
                    ],
                ],
                ...$baseConfig,
                'expectedPurchasePrice' => 1262.2, // (121.99 * 2 + 99.9 * 10 * 0.8) * 1.21 = 1,262.2478 -> round(1) -> 1,262.2
            ],
            'D04: Package price (minimalAmount 5, isPackage=true) with both base & discounted rows' => [
                'ppv' => [
                    [
                        'amount' => 10,
                        'prices' => [
                            5 => [
                                'price' => FactoryUtil::makePrice(141, 21, 5, 0, isPackage: true),
                                'discounted' => FactoryUtil::makePrice(141, 21, 5, 15, isPackage: true)
                            ]
                        ]
                    ],
                ],
                ...$baseConfig,
                'expectedPurchasePrice' => 1450.2, // 141 * 10 * 0.85 * 1.21 = 1,450.185 -> round(1) -> 1,450.2
            ],
            'D05: Discount price' => [
                'ppv' => [
                    [
                        'amount' => 10,
                        'prices' => [
                            1 => [
                                'price' => FactoryUtil::makePrice(100, 21, 1, discount: 0),
                            ]
                        ]
                    ],
                ],
                ...$baseConfig,
                'clientDiscount' => 15,
                'expectedPurchasePrice' => 1028.5, // 10 * 100 * 0.85 * 1.21 = 1,028.5
            ],
            'D06: Discount price & Product Price' => [
                'ppv' => [
                    [
                        'amount' => 10,
                        'prices' => [
                            1 => [
                                'price' => FactoryUtil::makePrice(100, 21, 1, discount: 0),
                                'discounted' => FactoryUtil::makePrice(100, 21, 1, discount: 10)
                            ]
                        ]
                    ],
                ],
                ...$baseConfig,
                'clientDiscount' => 15,
                'expectedPurchasePrice' => 907.5,
                /**
                 * How to calculate:
                 *
                 * formula:
                 * pcs * price + 21% - (clientDiscount + productDiscount)
                 *
                 * formula used with this example:
                 * step 1:
                 * 10pcs * 100 (price) + 21% (vat) - (15 + 10)
                 *
                 * step 2:
                 * 1000 + 21% - 25%
                 *
                 * step 3:
                 * 1210 - 25% = 907.5
                 *
                 **/

            ],
        ];
    }


    public static function comboUseCase(): array
    {
        $comboPpv = [
            [ // 4 * 50 * 1.21 = 242.0
                'amount' => 4,
                'prices' => [
                    1 => [
                        'price' => FactoryUtil::makePrice(50, 21, 1, discount: 0),
                        'discounted' => null,
                    ],
                ]
            ],
            [ // 1 * 93 * 1.15 = 106.95
                'amount' => 1,
                'prices' => [
                    1 => [
                        'price' => FactoryUtil::makePrice(93, 15, 1, discount: 0),
                        'discounted' => null,
                    ],
                ]
            ],
            [ // 2 * 105 * 0.80 * 1.21 = 203.28
                'amount' => 2,
                'prices' => [
                    1 => [
                        'price' => FactoryUtil::makePrice(105, 21, 1, discount: 0),
                        'discounted' => FactoryUtil::makePrice(105, 21, 1, discount: 20),
                    ],
                ]
            ],
        ];

        return [
            'V01: Combo integration row' => [
                'ppv' => $comboPpv,
                'transportation' => FactoryUtil::makeTransportation(99, 21, INF),
                'paymentType' => FactoryUtil::makePaymentType(0, 0, 0),

                'expectedSumPV' => 552.2, // 242 + 106.95 + 203.28 = 552.23 -> round(1) -> 552.2
                'expectedPriceByVat15' => 107.0,
                'expectedPriceByVat21' => 445.3,
                'expectedTotalWithServices' => 672.0, // 552,23 [with vat (15% and 21%)]  + (99 + 21%) = 672.02 -> round(1) -> 672.0
                'expectedTotalWithServicesEUR' => 26.88, // 672.09 * 0.04 = 26.8836 -> round(2) -> 26.88

                'clientDiscount' => 15,
                'withClientDiscountExpectedSumPV' => 581.6, // 461,7725 + (99 +21%) = 581,5625 -> round(1) -> 581,6
                'withClientDiscountExpectedPriceByVat15' => 90.9, // 106,95 * 0.85 = 90.9075 -> round(1) -> 90.9
                'withClientDiscountExpectedPriceByVat21' => 370.9, // (242 - 15%) + (254,1 - (15+20)%) = 370.865 -> round(1) -> 370.9
                'withClientDiscountExpectedTotalWithServices' => 581.6, //(242-15%) + (106,95-15%) + (254,1 - (20 + 15)%) = 461,7725 + (99 + 21%) = 581,5625 -> round(1) ->581,6
                'withClientDiscountExpectedTotalWithServicesEUR' => 23.26, // assertion above * 0.04 = 23.2625 -> round(2) -> 23,26
            ],
        ];
    }
}
