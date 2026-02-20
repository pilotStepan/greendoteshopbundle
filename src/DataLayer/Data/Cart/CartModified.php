<?php

namespace Greendot\EshopBundle\DataLayer\Data\Cart;

class CartModified
{
    public function __construct(
        public string $currency,
        public string $value,
        /**
         * @var CartItem[]
         */
        public array $items,
    ){}

}