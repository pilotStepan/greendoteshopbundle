<?php

namespace Greendot\EshopBundle\Affiliate;

class CancelAffiliateEntry
{
    public function __construct(public int $purchaseId) {}
}