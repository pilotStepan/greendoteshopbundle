<?php

namespace Greendot\EshopBundle\DataLayer\Data\Checkout;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;

class BeginCheckout
{
    public function __construct(
        public string  $currency,
        public float   $value,
        /** @var DataLayerItem[] */
        public array   $items,
    ) {}
}