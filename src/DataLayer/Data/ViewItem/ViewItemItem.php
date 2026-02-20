<?php

namespace Greendot\EshopBundle\DataLayer\Data\ViewItem;

use Greendot\EshopBundle\DataLayer\Data\DataParseTrait;
use JsonSerializable;
class ViewItemItem implements \JsonSerializable
{
    use DataParseTrait;
    public function __construct(
        private readonly string $item_id,
        private readonly string $item_name,
        private readonly string $item_brand,
        private readonly string $item_variant,
        private readonly float  $priceVat,
        private readonly float  $priceNoVat,
        private readonly int    $quantity,
        private readonly array  $categories,

        private readonly array $parameters
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