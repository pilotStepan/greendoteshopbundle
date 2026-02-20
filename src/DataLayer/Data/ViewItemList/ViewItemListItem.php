<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItemList;

use Greendot\EshopBundle\DataLayer\Data\DataParseTrait;
use JsonSerializable;

class ViewItemListItem implements JsonSerializable
{
    use DataParseTrait;
    public function __construct(
        private readonly string $item_id,
        private readonly string $item_name,
        private readonly int $index,
        private readonly float $priceVat,
        private readonly float $priceNoVat,
        private readonly int    $quantity,
        private readonly string $item_brand,
        private readonly array  $categories,
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