<?php

namespace Greendot\EshopBundle\Invoice\Data;

use Greendot\EshopBundle\Money\Money;

class InvoiceTransportationData
{
    public function __construct(
        public string   $name,
        public float    $price,
        public float    $priceSecondary,
        public float    $priceNoVat,
        public float    $priceNoVatSecondary,
        public ?string  $branchName,

        public ?Money   $priceMoney = null,
        public ?Money   $priceMoneySecondary = null,
        public ?Money   $priceNoVatMoney = null,
        public ?Money   $priceNoVatMoneySecondary = null,
    ) {}
}