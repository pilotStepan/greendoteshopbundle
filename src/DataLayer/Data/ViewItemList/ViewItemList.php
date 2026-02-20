<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItemList;

class ViewItemList
{

    public function __construct(
        public string $item_list_id,
        public string $item_list_name,
        /**
         * @var ViewItemListItem[]
         */
        public array $items = []
    ){}

}