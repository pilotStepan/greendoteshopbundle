<?php

namespace Greendot\EshopBundle\DataLayer\Data\Cart;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;

class CartModified
{
    public function __construct(
        public string $currency,
        public float $value,
        /**
         * @var DataLayerItem[]
         */
        public array $items,
    ){}

}