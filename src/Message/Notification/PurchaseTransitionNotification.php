<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Message\Notification;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
readonly class PurchaseTransitionNotification
{
    public function __construct(
        public int    $purchaseId,
        public string $transition,
        public string $alias,
    ) {}
}
