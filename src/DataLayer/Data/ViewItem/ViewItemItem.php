<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItem;

use Greendot\EshopBundle\DataLayer\Data\DataParseTrait;
use JsonSerializable;
class ViewItemItem implements \JsonSerializable
{
    use DataParseTrait;
    public function __construct(
        public string $item_id,
        public string $item_name,
        public string $item_brand,
        public string $item_variant,
        public float  $priceVat,
        public float  $priceNoVat,
        public int    $quantity,
        public array  $categories,

        public array $parameters
    ){}

    public function jsonSerialize(): mixed
    {
        $base = [
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'item_brand' => $this->item_brand,
            'item_variant' => $this->item_variant,
            'priceVat' => $this->priceVat,
            'priceNoVat' => $this->priceNoVat,
            'quantity' => $this->quantity,
            'parameters' => $this->parameters
        ];

        return array_merge($base, $this->serializeCategories($this->categories));

    }


}