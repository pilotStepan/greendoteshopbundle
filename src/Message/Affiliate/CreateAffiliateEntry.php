<?php

namespace Greendot\EshopBundle\Message\Affiliate;

class CreateAffiliateEntry
{
    public function __construct(
        public int $purchaseId,
    ) {}
}