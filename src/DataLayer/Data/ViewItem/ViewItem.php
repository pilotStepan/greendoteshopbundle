<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItem;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;

class ViewItem
{
    public function __construct(
        public string $currency,
        public float  $priceVat,
        public float  $priceNoVat,
        public float  $valueVat,
        public float  $valueNoVat,
        /**
         * @var DataLayerItem[]
         */
        public array  $items
    )
    {
    }
}