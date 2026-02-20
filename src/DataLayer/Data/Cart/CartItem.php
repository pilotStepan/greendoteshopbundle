<?php

namespace Greendot\EshopBundle\DataLayer\Data\Cart;

class CartItem
{
    public function __construct(
        public string $item_id,
        public string $item_name,
        public string $quantity,
        public float $price,
        public string $item_brand
    ){}
}