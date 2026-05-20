<?php

namespace Greendot\EshopBundle\DataLayer\Data\Wishlist;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;

class AddToWishlist
{
    public function __construct(
        public string $currency,
        public float  $value,
        /** @var DataLayerItem[] */
        public array  $items,
    ) {}
}