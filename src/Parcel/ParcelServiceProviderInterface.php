<?php

namespace Greendot\EshopBundle\Parcel;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Parcel\Exception\ParcelServiceNotFoundException;

interface ParcelServiceProviderInterface
{
    /**
     * @throws ParcelServiceNotFoundException If no parcel service supports this purchase's transportation.
     */
    public function getByPurchase(Purchase $purchase): ParcelServiceInterface;
}
