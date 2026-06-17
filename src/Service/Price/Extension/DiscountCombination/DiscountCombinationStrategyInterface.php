<?php

namespace Greendot\EshopBundle\Service\Price\Extension\DiscountCombination;

interface DiscountCombinationStrategyInterface
{
    public function combine(float $productDiscount, ?float $clientDiscount): float;
}