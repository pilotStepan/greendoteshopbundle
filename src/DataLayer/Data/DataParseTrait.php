<?php

namespace Greendot\EshopBundle\DataLayer\Data;

trait DataParseTrait
{
    private function serializeCategories(array $categories): array
    {
        $base = [];
        if (isset($categories[0])) {
            $categories = $categories[0];
            $categories = array_reverse($categories);
            foreach ($categories as $key => $category) {
                $keyName = "item_category". ($key > 0 ? $key+1 : '');
                $base[$keyName] = $category;
            }
        }
        return $base;
    }
}