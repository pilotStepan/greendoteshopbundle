<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItemList;

use Greendot\EshopBundle\DataLayer\Data\DataParseTrait;
use JsonSerializable;

class ViewItemListItem implements JsonSerializable
{
    use DataParseTrait;
    public function __construct(
        public string $item_id,
        public string $item_name,
        public int $index,
        public float $priceVat,
        public float $priceNoVat,
        public int    $quantity,
        public string $item_brand,
        public array  $categories,
    )
    {
    }

    public function jsonSerialize(): array
    {
        $base = [
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'index' => $this->index,
            'price_vat' => $this->priceVat,
            'price_no_vat' => $this->priceNoVat,
            'quantity' => $this->quantity,
            'item_brand' => $this->item_brand,
        ];

        return array_merge($base, $this->serializeCategories($this->categories));
    }
}