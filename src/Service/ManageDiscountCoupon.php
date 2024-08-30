<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Voucher;

class ManageDiscountCoupon
{
    private const DISCOUNT_COUPON = 'discountCoupon';

    public function validateDiscountCoupon(Voucher $voucher): bool
    {
        return $this->isDateUntilValid($voucher);
    }

    private function isDateUntilValid(Voucher $voucher): bool
    {
        $dateUntil = $voucher->getDateUntil();
        $currentDate = new \DateTime();
        return $dateUntil > $currentDate;
    }
}