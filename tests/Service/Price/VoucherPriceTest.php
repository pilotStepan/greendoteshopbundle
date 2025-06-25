<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Enum\VoucherCalculationType as VouchCalc;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;
use PHPUnit\Framework\Attributes\DataProviderExternal;

class VoucherPriceTest extends PriceCalculationTestCase
{
    #[DataProviderExternal(VoucherDataProvider::class, 'vouchers')]
    public function testVouchers(
        array           $ppv,
        array           $vouchers,
        ?Transportation $transportation,
        ?PaymentType    $paymentType,
        float           $expectedVouchersUsedValue,
        float           $expectedPurchasePriceWithoutVoucher,
        float           $expectedPurchasePriceWithVoucher,
        float           $expectedPurchasePriceWithVoucherToMinus,
    ): void
    {
        // ARRANGE
        $purchase = $this->createPurchase($ppv, null, $vouchers);
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getPaymentType')->willReturn($paymentType);
        $this->handlingPriceRepository->method('GetByDate')
            ->willReturnCallback(function ($entity) {
                return $entity->getHandlingPrices()->first();
            });


        // ACT
        $pp = $this->createPurchasePrice(
            $purchase,
            VatCalc::WithVAT,
            DiscCalc::WithoutDiscount,
            FactoryUtil::czk()
        );


        // ASSERT
        $this->assertEqualsWithDelta(
            $expectedVouchersUsedValue,
            $pp->getVouchersUsedValue(),
            0.001,
            "Vouchers used value mismatch"
        );

        $pp->setVoucherCalculationType(VouchCalc::WithoutVoucher);
        $this->assertEqualsWithDelta(
            $expectedPurchasePriceWithoutVoucher,
            $pp->getPrice(true),
            0.001,
            "Purchase price without voucher mismatch"
        );

        $pp->setVoucherCalculationType(VouchCalc::WithVoucher);
        $this->assertEqualsWithDelta(
            $expectedPurchasePriceWithVoucher,
            $pp->getPrice(true),
            0.001,
            "Purchase price with voucher mismatch"
        );

        $pp->setVoucherCalculationType(VouchCalc::WithVoucherToMinus);
        $this->assertEqualsWithDelta(
            $expectedPurchasePriceWithVoucherToMinus,
            $pp->getPrice(true),
            0.001,
            "Purchase price with voucher to minus mismatch"
        );
    }
}