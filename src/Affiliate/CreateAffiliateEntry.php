<?php

namespace Greendot\EshopBundle\Affiliate;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
class CreateAffiliateEntry
{
    public function __construct(public int $purchaseId) {}
}