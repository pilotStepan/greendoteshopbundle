<?php

namespace Greendot\EshopBundle\DataLayer\Data\Purchase;

use Greendot\EshopBundle\DataLayer\Data\DataParseTrait;
use JsonSerializable;
class PurchaseItem implements \JsonSerializable
{
    use DataParseTrait;
    public function __construct(
        public string $item_id,
        public string $item_name,
        public float $priceVat,
        public float $priceNoVat,
        public int $quantity,
        public array $categories,
    ){}

    public function jsonSerialize(): mixed
    {
        $base = [
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'priceVat' => $this->priceVat,
            'priceNoVat' => $this->priceNoVat,
            'quantity' => $this->quantity,
        ];
        return array_merge($base, $this->serializeCategories($this->categories));
    }
}