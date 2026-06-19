<?php

namespace Greendot\EshopBundle\Parcel\Message;

class UpdateDeliveryStatusMessage
{
    public function __construct(public int $purchaseId) {}
}