<?php

namespace Greendot\EshopBundle\DataLayer\Data;

use JsonSerializable;

class DataLayerItem implements JsonSerializable
{
    use DataParseTrait;

    public function __construct(
        public string  $item_id,
        public string  $item_name,
        public int     $quantity,
        public float   $priceVat,
        public float   $priceNoVat,
        public string  $item_brand = 'Unknown',
        public array   $categories = [],
        public ?string $item_variant = null,
        public ?array  $parameters = null,
        public ?int    $index = null,
    ) {}

    public function jsonSerialize(): array
    {
        $base = [
            'item_id'    => $this->item_id,
            'item_name'  => $this->item_name,
            'item_brand' => $this->item_brand,
            'priceVat'   => $this->priceVat,
            'priceNoVat' => $this->priceNoVat,
            'quantity'   => $this->quantity,
        ];

        if ($this->item_variant !== null) {
            $base['item_variant'] = $this->item_variant;
        }
        if ($this->parameters !== null) {
            $base['parameters'] = $this->parameters;
        }
        if ($this->index !== null) {
            $base['index'] = $this->index;
        }

        return array_merge($base, $this->serializeCategories($this->categories));
    }
}