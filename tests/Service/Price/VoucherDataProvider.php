<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;


class VoucherDataProvider
{
    public static function vouchers(): array
    {
        $ppvBase = [
            [ // P = 100, VAT = 0 → getPrice(true) == 100
                'amount' => 1,
                'prices' => [
                    [
                        'price' => FactoryUtil::makePrice(100, 0),
                        'discounted' => null,
                    ]
                ],
            ],
        ];

        // services
        $svc0 = FactoryUtil::makeTransportation(0, 0, 0);
        $pay0 = FactoryUtil::makePaymentType(0, 0, 0);
        $svc20 = FactoryUtil::makeTransportation(20, 0, INF);
        $pay20 = FactoryUtil::makePaymentType(20, 0, INF);

        return [
            /**
             * price stays unchanged, no services.
             */
            'TR01_NoVoucher_V0' => [
                'ppv' => $ppvBase,
                'vouchers' => [],
                'transportation' => $svc0,
                'paymentType' => $pay0,

                'expectedVouchersUsedValue' => 0.0,
                'expectedPurchasePriceWithoutVoucher' => 100.0,
                'expectedPurchasePriceWithVoucher' => 100.0,
                'expectedPurchasePriceWithVoucherToMinus' => 100.0,
            ],

            /**
             * With vouchers present but VoucherCalculationType = WithoutVoucher
             */
            'TR02_WithoutVoucherType_V50' => [
                'ppv' => $ppvBase,
                'vouchers' => [FactoryUtil::makeVoucher(50)], // V = 50
                'transportation' => $svc0,
                'paymentType' => $pay0,

                'expectedVouchersUsedValue' => 50.0,
                'expectedPurchasePriceWithoutVoucher' => 100.0,
                'expectedPurchasePriceWithVoucher' => 50.0,
                'expectedPurchasePriceWithVoucherToMinus' => 50.0,
            ],

            /**
             * P = 100, V = 30
             */
            'TR03_Voucher30_LtP' => [
                'ppv' => $ppvBase,
                'vouchers' => [FactoryUtil::makeVoucher(30)], // V = 30
                'transportation' => $svc0,
                'paymentType' => $pay0,

                'expectedVouchersUsedValue' => 30.0,
                'expectedPurchasePriceWithoutVoucher' => 100.0,
                'expectedPurchasePriceWithVoucher' => 70.0,
                'expectedPurchasePriceWithVoucherToMinus' => 70.0,
            ],

            /**
             * P = 100, V = 100
             */
            'TR04_Voucher100_EqP' => [
                'ppv' => $ppvBase,
                'vouchers' => [FactoryUtil::makeVoucher(100)],
                'transportation' => $svc0,
                'paymentType' => $pay0,

                'expectedVouchersUsedValue' => 100.0,
                'expectedPurchasePriceWithoutVoucher' => 100.0,
                'expectedPurchasePriceWithVoucher' => 0.0,
                'expectedPurchasePriceWithVoucherToMinus' => 0.0,
            ],

            /**
             * P = 100, V = 150
             */
            'TR05_Voucher150_GtP' => [
                'ppv' => $ppvBase,
                'vouchers' => [FactoryUtil::makeVoucher(150)],
                'transportation' => $svc0,
                'paymentType' => $pay0,

                'expectedVouchersUsedValue' => 150.0,
                'expectedPurchasePriceWithoutVoucher' => 100.0,
                'expectedPurchasePriceWithVoucher' => 0.0,   // clamped
                'expectedPurchasePriceWithVoucherToMinus' => -50.0, // allowed below 0
            ],

            /**
             * P = 100, Services = 40 → 140; V = 80
             */
            'TR06_WithServices_V80' => [
                'ppv' => $ppvBase,
                'vouchers' => [
                    FactoryUtil::makeVoucher(40),
                    FactoryUtil::makeVoucher(40),
                ],
                'transportation' => $svc20,
                'paymentType' => $pay20,

                'expectedVouchersUsedValue' => 80.0,
                'expectedPurchasePriceWithoutVoucher' => 140.0,
                'expectedPurchasePriceWithVoucher' => 60.0,
                'expectedPurchasePriceWithVoucherToMinus' => 60.0,
            ],

            /**
             * P+S = 140, V = 150  (80 + 70)
             */
            'TR07_WithServices_V150_GtTotal' => [
                'ppv' => $ppvBase,
                'vouchers' => [
                    FactoryUtil::makeVoucher(80),
                    FactoryUtil::makeVoucher(70),
                ],
                'transportation' => $svc20,
                'paymentType' => $pay20,

                'expectedVouchersUsedValue' => 150.0,
                'expectedPurchasePriceWithoutVoucher' => 140.0,
                'expectedPurchasePriceWithVoucher' => 0.0,
                'expectedPurchasePriceWithVoucherToMinus' => -10.0,
            ],
        ];
    }
}
