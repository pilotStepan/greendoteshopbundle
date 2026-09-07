<?php

namespace Greendot\EshopBundle\Invoice\Data;

use Greendot\EshopBundle\Money\Money;

class InvoiceItemData
{
    public function __construct(
        public string   $name,
        public int      $amount,
        public ?string   $externalId,

        public float    $vatPercentage,

        public float    $priceNoVat,
        public float    $priceNoVatSecondary,

        public float    $priceVat,
        public float    $priceVatSecondary,

        public float    $priceNoVatNoDiscount,
        public float    $priceNoVatNoDiscountSecondary,

        public float    $priceVatNoDiscount,
        public float    $priceVatNoDiscountSecondary,

        public string   $parametersLabel = '',

        public ?Money   $priceNoVatMoney = null,
        public ?Money   $priceNoVatMoneySecondary = null,
        public ?Money   $priceVatMoney = null,
        public ?Money   $priceVatMoneySecondary = null,
        public ?Money   $priceNoVatNoDiscountMoney = null,
        public ?Money   $priceNoVatNoDiscountMoneySecondary = null,
        public ?Money   $priceVatNoDiscountMoney = null,
        public ?Money   $priceVatNoDiscountMoneySecondary = null,
    ) { }
}