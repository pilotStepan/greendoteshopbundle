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
                ...$baseConfig,
                'ppv' => $s01Ppv,
                'transportation' => FactoryUtil::makeTransportation(99, 21, 300),
                'paymentType' => FactoryUtil::makePaymentType(30, 21, 500),
                'expectedPurchasePrice' => 252.9, // (80 + 99 + 30) * 1.21 = 252.89 -> round(1) -> 252.9
                'expectedTransportationPrice' => 119.8, // 99 * 1.21 = 119.79 -> round(1) -> 119.8
                'expectedPaymentPrice' => 36.3, // 30 * 1.21 = 36.3
            ],
            'S02: Payment paid, transport free' => [
                ...$baseConfig,
                'ppv' => $s01Ppv,
                'transportation' => FactoryUtil::makeTransportation(99, 21, 79.9),
                'paymentType' => FactoryUtil::makePaymentType(30, 21, 80.1), // TODO: free from price pocita se bez DPH (?)
                'expectedPurchasePrice' => 133.1, // (80 + 0 + 30) * 1.21 = 133.1
                'expectedTransportationPrice' => 0.0,
                'expectedPaymentPrice' => 36.3,
            ],
            'S03-EUR: Currency conversion and rounding' => [
                ...$baseConfig,
                'ppv' => $s03Ppv,
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
                ...$baseConfig,
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
                'expectedPurchasePrice' => 1210.0 // 100 * 10 * 1.21 = 1,210.0
            ],
            'D02: Only a discounted price exists (no base price)' => [
                ...$baseConfig,
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
                'expectedPurchasePrice' => 1197.9, // 100 * 11 * 0.90 * 1.21 = 1,197.9
            ],
            'D03: Both normal and discounted rows for the same minimalAmount' => [
                ...$baseConfig,
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
                'expectedPurchasePrice' => 980.1, // 100 * 9 * 1.21 * 0.90 = 980.1
            ],
            'D04: Two tiers, discount only on the second tier' => [
                /* TODO: upresnit chovani
                    Expected: 1262.2 (2 price + 10 discounted)
                    Actual:   1771.3 (12 price)
                */
                ...$baseConfig,
                'ppv' => [
                    [
                        'amount' => 12,
                        'prices' => [
                            1 => [
                                'price' => FactoryUtil::makePrice(121.99, 21, 1, discount: 0),
                            ],
                            10 => [
                                'discounted' => FactoryUtil::makePrice(99.9, 21, 10, discount: 20, isPackage: true)
                            ]
                        ]
                    ],
                ],
                'expectedPurchasePrice' => 1262.2, // (121.99 * 2 + 99.9 * 10 * 0.8) * 1.21 = 1,262.2478 -> round(1) -> 1,262.2
            ],
            'D04: Package price (minimalAmount 5, isPackage=true) with both base & discounted rows' => [
                ...$baseConfig,
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
                'expectedPurchasePrice' => 1450.2, // 141 * 10 * 0.85 * 1.21 = 1,450.185 -> round(1) -> 1,450.2
            ],
            'D05: Discount price' => [
                ...$baseConfig,
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
                'clientDiscount' => 15,
                'expectedPurchasePrice' => 1028.5, // 10 * 100 * 0.85 * 1.21 = 1,028.5
            ],
            'D06: Discount price & Product Price' => [
                ...$baseConfig,
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
                'clientDiscount' => 15,
                'expectedPurchasePrice' => 925.7, // 100 * 10 * 0.90 * 0.85 * 1.21 = 925.65 -> round(1) -> 925.7 // FIXME: Actual:907.5
                // (10 * 100) - 0.90


                // - 15% - 10% + 21%


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

                'expectedSumPV' => 552.2, // 242 + 106.95 + 203.28 = 552.23 -> round(1) -> 552.2 // FIXME: Actual:552.3
                'expectedPriceByVat15' => 107.0, // pocita se jen druhy produkt
                'expectedPriceByVat21' => 445.2, // pocita se jen prvni a treti produkt // FIXME: treti produkt se nepocita (vat 21.000...4 != vat 21.0)
                'expectedTotalWithServices' => 672.1, // 552.3 + 99 * 1.21 = 672.09 -> round(1) -> 672.1
                'expectedTotalWithServicesEUR' => 26.88, // 672.09 * 0.04 = 26.8836 -> round(2) -> 26.88

                'clientDiscount' => 15,
                'withClientDiscountExpectedSumPV' => 469.4, // 552.23 * 0.85 = 469.3955 -> round(1) -> 469.4
                'withClientDiscountExpectedPriceByVat15' => 91.0, // 107.0 * 0.85 = 90.95 -> round(1) -> 91.0
                'withClientDiscountExpectedPriceByVat21' => 378.4, // 445.2 * 0.85 = 378.42 -> round(1) -> 378.4
                'withClientDiscountExpectedTotalWithServices' => 571.3, // 672.09 * 0.85 = 571.2765 -> round(1) -> 571.3
                'withClientDiscountExpectedTotalWithServicesEUR' => 22.85, // 26.8836 * 0.85 = 22.85106 -> round(2) -> 22.85
            ],
        ];
    }
}
