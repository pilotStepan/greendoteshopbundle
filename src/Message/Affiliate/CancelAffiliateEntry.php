<?php

namespace Greendot\EshopBundle\Message\Affiliate;

class CancelAffiliateEntry
{
    public function __construct(
        public int $purchaseId,
    ) {}
}