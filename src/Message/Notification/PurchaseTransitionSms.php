<?php

namespace Greendot\EshopBundle\Message\Notification;

readonly class PurchaseTransitionSms
{
    public function __construct(
        public int    $purchaseId,
        public string $transition,
    ) {}
}