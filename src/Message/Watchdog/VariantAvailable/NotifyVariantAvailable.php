<?php

namespace Greendot\EshopBundle\Message\Watchdog\VariantAvailable;

class NotifyVariantAvailable
{
    public function __construct(public int $productVariantId) {}
}