<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderPaymentData
{
    public function __construct(
        public int     $action,
        public string  $country,
        public string  $name,
        public string  $description,
        public string  $priceCzk,
        public string  $priceEur,
        public ?string $bankNumber,
        public ?string $bankAccount,
        public ?string $bankName,
        public ?string $bankIban,
    ) {}
}