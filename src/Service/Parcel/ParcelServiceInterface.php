<?php

namespace Greendot\EshopBundle\Service\Parcel;

use Greendot\EshopBundle\Entity\Project\Purchase;

interface ParcelServiceInterface
{
    public function createParcel(Purchase $purchase): ?string;

    public function getParcelStatus(Purchase $purchase): ?array;
}