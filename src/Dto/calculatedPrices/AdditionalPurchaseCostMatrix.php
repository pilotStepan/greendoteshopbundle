<?php

namespace Greendot\EshopBundle\Dto\calculatedPrices;

use Greendot\EshopBundle\Entity\Project\AdditionalPurchaseCost;
use Greendot\EshopBundle\Service\Price\AdditionalPurchaseCostCalculatedPrice;


class AdditionalPurchaseCostMatrix
{
    private $data = [];

    public function getData(){
        return $this->data;
    }

    public function addFromArray(array $array, string $key): void
    {
        foreach ($array as $item){
            assert($item instanceof AdditionalPurchaseCostCalculatedPrice);
            $this->add($key, $item->id, $item->additionalPurchaseCost, $item->price);
        }
    }

    public function add(
        string                 $key,
        int                    $id,
        AdditionalPurchaseCost $additionalPurchaseCost,
        ?float                 $price = null
    ): void
    {
        if (!isset($this->data[$id])) {
            $this->data[$id] = ['name' => $additionalPurchaseCost->getName(), 'description' => $additionalPurchaseCost->getDescription(), 'prices' => []];
        }
        $prices = $this->data[$id]['prices'];
        $prices[$key] = $price;
        $this->data[$id]['prices'] = $prices;
    }
}
