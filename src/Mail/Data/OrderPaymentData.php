<?php

namespace Greendot\EshopBundle\Mail\Data;

use Greendot\EshopBundle\Money\Money;

class OrderPaymentData
{
    public function __construct(
        public int     $action,
        public string  $country,
        public string  $name,
        public string  $description,
        public string  $mailDescription,
        public string  $priceCzk,
        public string  $priceEur,
        public ?string $bankNumber,
        public ?string $bankAccount,
        public ?string $bankName,
        public ?string $bankIban,

        public ?Money   $priceMoneyPrimary = null,
        public ?string  $pricePrimaryFormatted = null,
        public ?Money   $priceMoneySecondary = null,
        public ?string  $priceSecondaryFormatted = null,
    ) {}
}