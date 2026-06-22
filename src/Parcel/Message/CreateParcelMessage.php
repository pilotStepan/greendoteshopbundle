<?php

namespace Greendot\EshopBundle\Parcel\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
class CreateParcelMessage
{
    public function __construct(public int $purchaseId) {}
}