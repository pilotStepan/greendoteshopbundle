<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Message\Notification;

readonly class PurchaseTransitionNotification
{
    public function __construct(
        public int    $purchaseId,
        public string $transition,
        /** @var string[] */
        public array  $handlerAliases,
    ) {}
}
