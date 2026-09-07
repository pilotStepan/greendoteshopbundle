<?php

namespace Greendot\EshopBundle\Invoice\Data;

use Greendot\EshopBundle\Money\Money;

class VatCategoryData
{
    public function __construct(
        public float    $percentage,
        public float    $base,
        public float    $baseSecondary,
        public float    $value,
        public float    $valueSecondary,

        public ?Money   $baseMoney = null,
        public ?Money   $baseMoneySecondary = null,
        public ?Money   $valueMoney = null,
        public ?Money   $valueMoneySecondary = null,
    ) {}
}