<?php

namespace Greendot\EshopBundle\Message\Notification;

class PurchaseDiscussionEmail
{
    public function __construct(
        public int    $purchaseId,
        public string $content,
    ) {}
}