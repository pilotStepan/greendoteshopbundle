<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;

class PriceUtils
{

    /**
     * Calculates the percentage amount, percentage, or full amount based on the provided parameters.
     *
     * - If `$fullAmount` and `$percentage` are given, returns the percentage amount.
     * - If `$fullAmount` and `$percentageAmount` are given, returns the percentage.
     * - If `$percentage` and `$percentageAmount` are given, returns the full amount.
     * - Returns `null` if insufficient parameters are provided.
     *
     * @param float|null $fullAmount         The base amount.
     * @param float|null $percentage         The percentage value.
     * @param float|null $percentageAmount   The amount representing the percentage of the full amount.
     *
     * @return float|null The calculated value or null if not enough parameters are provided.
     */
    public function calculatePercentage(
        ?float $fullAmount = null,
        ?float $percentage = null,
        ?float $percentageAmount = null
    ): ?float
    {
        if (!is_null($fullAmount) && !is_null($percentage)) {
            return ($fullAmount * $percentage) / 100;
        }

        if (!is_null($fullAmount) && !is_null($percentageAmount) && $fullAmount > 0) {
            return ($percentageAmount / $fullAmount) * 100;
        }

        if (!is_null($percentage) && !is_null($percentageAmount) && $percentage > 0) {
            return ($percentageAmount * 100) / $percentage;
        }

        return null;
    }

    public function convertCurrency(?float $price, Currency $currency): float
    {
        if (is_null($price)) return 0;
        $price = $price * $currency->getConversionRate();
        return round($price, $currency->getRounding());
    }
}