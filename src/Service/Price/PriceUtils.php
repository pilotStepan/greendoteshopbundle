<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\ConversionRateRepository;

class PriceUtils
{
    public function __construct(
        private readonly ConversionRateRepository $conversionRateRepository
    ){}

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

    public function getConversionRate(Currency $currency, ?Purchase $purchase = null): ConversionRate
    {
        $date = new \DateTime("now");

        //TODO: This is not reusable way to do it.
        if ($purchase && !in_array($purchase->getState(),['draft', 'wishlist', 'new'])){
            $date = $purchase->getDateIssue();
        }

        $conversionRate = $this->conversionRateRepository->getByDate($currency, $date);

        //TODO: Is there a better way to do this? So its fail-safe
        if (!$conversionRate){
            $conversionRate = new ConversionRate();
            $conversionRate->setCurrency($currency);
            $conversionRate->setRate(1);
            $conversionRate->setValidFrom($date);
        }

        return $conversionRate;
    }

    public function convertCurrency(?float $price, Currency $currency, float|ConversionRate $conversionRate): float
    {
        if ($conversionRate instanceof ConversionRate) $conversionRate = $conversionRate->getRate();

        if (is_null($price)) return 0;
        $price = $price * $conversionRate;
        return round($price, $currency->getRounding());
    }
}