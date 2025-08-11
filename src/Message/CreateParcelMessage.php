<?php

namespace Greendot\EshopBundle\Message;

class CreateParcelMessage
{
    public function __construct(
        public int $purchaseId,
    ) {}
}