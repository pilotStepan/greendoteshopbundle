<?php

namespace Greendot\EshopBundle\Parcel\Message;

class CreateParcelMessage
{
    public function __construct(public int $purchaseId) {}
}