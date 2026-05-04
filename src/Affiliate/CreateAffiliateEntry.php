<?php

namespace Greendot\EshopBundle\Affiliate;

class CreateAffiliateEntry
{
    public function __construct(public int $purchaseId) {}
}