<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Parcel\Exception\PermanentParcelException;

trait CzechPostCurrencyGuardTrait
{
    /**
     * Czech Post's B2B-ZSKService API is only confirmed to accept CZK; reject anything else
     * instead of forwarding a currency it may silently misinterpret.
     */
    private function assertCzkCurrency(Money $money, Purchase $purchase): void
    {
        if ($money->iso !== 'CZK') {
            throw new PermanentParcelException(sprintf(
                'Czech Post only supports CZK, purchase %s is in %s',
                $purchase->getId(),
                $money->iso,
            ));
        }
    }
}
