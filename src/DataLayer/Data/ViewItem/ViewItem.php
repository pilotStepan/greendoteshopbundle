<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItem;

class ViewItem
{
    public function __construct(
        public string $currency,
        public float  $priceVat,
        public float  $priceNoVat,
        /**
         * @var ViewItemItem[]
         */
        public array  $items
    )
    {
    }
}