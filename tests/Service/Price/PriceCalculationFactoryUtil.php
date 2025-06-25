<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\HandlingPrice;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Transportation;

class PriceCalculationFactoryUtil
{
    public static function makePrice(
        float  $unitPrice,
        float  $vatPercentage,
        ?int   $minimalAmount = null,
        ?float $discount = null,
        ?float $minPrice = null,
        ?bool  $isPackage = null,
    ): Price
    {
        return (new Price())
            ->setPrice($unitPrice)
            ->setVat($vatPercentage)
            ->setMinimalAmount($minimalAmount ?? 1)
            ->setDiscount($discount ?? 0.0)
            ->setMinPrice($minPrice ?? 0.0)
            ->setIsPackage($isPackage ?? false)
            ->setValidFrom(new \DateTime('-1 day'))
            ->setValidUntil(new \DateTime('+1 day'));
    }

    public static function makeTransportation(
        float  $price,
        float  $vatPercentage,
        ?float $freeFromPrice,
        ?float $discount = 0.0,
    ): Transportation
    {
        return (new Transportation())
            ->addHandlingPrice(
                self::makeHandlingPrice(
                    $price,
                    $vatPercentage,
                    $freeFromPrice,
                    $discount
                )
            );
    }

    public static function makePaymentType(
        float  $price,
        float  $vatPercentage,
        ?float $freeFromPrice,
        ?float $discount = 0.0,
    ): PaymentType
    {
        return (new PaymentType())
            ->addHandlingPrice(
                self::makeHandlingPrice(
                    $price,
                    $vatPercentage,
                    $freeFromPrice,
                    $discount
                )
            );
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

    private static function makeHandlingPrice(
        float                      $price,
        float                      $vat,
        float                      $freeFromPrice = INF,
        float                      $discount = 0.0,
    ): HandlingPrice
    {
        return (new HandlingPrice())
            ->setPrice($price)
            ->setVat($vat)
            ->setFreeFromPrice($freeFromPrice)
            ->setDiscount($discount)
            ->setValidFrom(new \DateTime('-1 day'))
            ->setValidUntil(new \DateTime('+1 day'))
        ;
    }
}