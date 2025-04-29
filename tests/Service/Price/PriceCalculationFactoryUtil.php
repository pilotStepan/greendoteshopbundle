<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Price;

class PriceCalculationFactoryUtil
{
    public static function makePrice(
        float  $unitPrice,
        float  $vatPercentage,
        ?int   $minimalAmount = null,
        ?float $minPrice = null,
        ?float $discount = null,
        ?bool  $isPackage = null,
    ): Price
    {
        $minimalAmount = $minimalAmount ?? 1;
        $minPrice = $minPrice ?? $unitPrice;
        $discount = $discount ?? 0.0;
        $isPackage = $isPackage ?? false;

        return (new Price())
            ->setPrice($unitPrice)
            ->setVat($vatPercentage)
            ->setMinimalAmount($minimalAmount)
            ->setDiscount($discount)
            ->setIsPackage($isPackage)
            ->setMinPrice($minPrice)
            ->setValidFrom(new \DateTime('-1 day'))
            ->setValidUntil(new \DateTime('+1 day'));
    }

    public static function czk(): Currency
    {
        // for testing purposes, rounding = 1
        return (new Currency())
            ->setConversionRate(1)
            ->setRounding(1)
            ->setIsDefault(1);
    }

    public static function eur(): Currency
    {
        // for testing purposes, rounding = 2
        return (new Currency())
            ->setConversionRate(0.04)
            ->setRounding(2)
            ->setIsDefault(0);
    }

}