<?php

namespace Greendot\EshopBundle\Message\Notification;

readonly class PurchaseTransitionSms
{
    public function __construct(
        private int    $purchaseId,
        private string $transition,
    ) {}

    public function getPurchaseId(): int
    {
        return $this->purchaseId;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }
}