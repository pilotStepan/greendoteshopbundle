<?php

namespace Greendot\EshopBundle\Parcel\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('parcel')]
class UpdateDeliveryStatusMessage
{
    public function __construct(public int $purchaseId) {}
}