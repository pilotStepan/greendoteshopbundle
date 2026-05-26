<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItemList;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;

class ViewItemList
{

    public function __construct(
        public string $item_list_id,
        public string $item_list_name,
        /**
         * @var DataLayerItem[]
         */
        public array $items = []
    ){}

}