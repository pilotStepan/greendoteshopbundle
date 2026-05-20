<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItem;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;

class ViewItem
{
    public function __construct(
        public string $currency,
        public float  $priceVat,
        public float  $priceNoVat,
        /**
         * @var DataLayerItem[]
         */
        public array  $items
    )
    {
    }
}