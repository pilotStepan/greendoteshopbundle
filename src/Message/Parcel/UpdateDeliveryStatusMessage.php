<?php

namespace Greendot\EshopBundle\Message\Parcel;

class UpdateDeliveryStatusMessage
{
    public function __construct(public int $purchaseId) {}
}