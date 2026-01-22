<?php

namespace Greendot\EshopBundle\Message\ProductVariant;

class NotifyVariantAvailable
{
    public function __construct(public int $productVariantId) {}
}