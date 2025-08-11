<?php

namespace Greendot\EshopBundle\Message\Parcel;

class CreateParcelMessage
{
    public function __construct(public int $purchaseId) {}
}