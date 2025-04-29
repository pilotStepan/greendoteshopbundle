<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;

class PriceUtils
{
    public function calculatePercentage(
        float $fullAmount = null,
        float $percentage = null,
        float $percentageAmount = null
    ): ?float
    {
        if (!is_null($fullAmount) && !is_null($percentage)) {
            return ($fullAmount * $percentage) / 100;
        }

        if (!is_null($fullAmount) && !is_null($percentageAmount)) {
            return ($percentageAmount / $fullAmount) * 100;
        }

        if (!is_null($percentage) && !is_null($percentageAmount)) {
            return ($percentageAmount * 100) / $percentage;
        }

        return null;
    }

    public function convertCurrency(float $price, Currency $currency): float
    {
        $price = $price * $currency->getConversionRate();
        return round($price, $currency->getRounding());
    }
}