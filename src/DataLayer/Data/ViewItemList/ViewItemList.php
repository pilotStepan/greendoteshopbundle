<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItemList;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;

class ViewItemList
{

    public function __construct(
        public string $item_list_id,
        public string $item_list_name,
        public float  $valueVat,
        public float  $valueNoVat,
        /**
         * @var DataLayerItem[]
         */
        public array $items = []
    ){}

}