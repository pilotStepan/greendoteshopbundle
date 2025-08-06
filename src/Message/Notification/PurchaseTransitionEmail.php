<?php

namespace Greendot\EshopBundle\Message\Notification;

readonly class PurchaseTransitionEmail
{
    public function __construct(
        public int    $purchaseId,
        public string $transition,
    ) {}
}