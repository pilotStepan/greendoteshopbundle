<?php

namespace Greendot\EshopBundle\DataLayer\Data\Purchase;

use Greendot\EshopBundle\DataLayer\Data\DataParseTrait;
use JsonSerializable;
class PurchaseItem implements \JsonSerializable
{
    use DataParseTrait;
    public function __construct(
        private readonly string $item_id,
        private readonly string $item_name,
        private readonly float $priceVat,
        private readonly float $priceNoVat,
        private readonly int $quantity,
        private readonly array $categories,
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