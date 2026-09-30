<?php

namespace Greendot\EshopBundle\Mail\Data;

use Greendot\EshopBundle\Money\Money;

class OrderTransportationData
{
    public function __construct(
        public string   $action,
        public string   $country,
        public string   $name,
        public string   $description,
        public string   $priceCzk,
        public string   $priceEur,
        public ?string  $branchName,
        public ?string  $mailDescription,

        public ?Money   $priceMoneyPrimary = null,
        public ?string  $pricePrimaryFormatted = null,
        public ?Money   $priceMoneySecondary = null,
        public ?string  $priceSecondaryFormatted = null,
    ) {}
}