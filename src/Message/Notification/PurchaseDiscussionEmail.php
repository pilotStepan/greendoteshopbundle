<?php

namespace Greendot\EshopBundle\Message\Notification;

use DateTimeImmutable;

class PurchaseDiscussionEmail
{
    public function __construct(
        public int               $purchaseId,
        public string            $content,
        public DateTimeImmutable $createdAt,
    ) {}
}