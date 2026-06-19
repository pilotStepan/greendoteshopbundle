<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItemListProduct;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;

class ViewItemListProduct
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