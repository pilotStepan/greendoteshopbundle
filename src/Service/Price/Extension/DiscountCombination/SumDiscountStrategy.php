<?php

namespace Greendot\EshopBundle\Service\Price\Extension\DiscountCombination;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('greendot_eshop.discount_combination_strategy', ['key' => 'sum'])]
class SumDiscountStrategy implements DiscountCombinationStrategyInterface
{
    public function combine(float $productDiscount, ?float $clientDiscount): float
    {
        return $productDiscount + ($clientDiscount ?? 0);
    }
}