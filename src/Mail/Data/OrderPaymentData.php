<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderPaymentData
{
    public function __construct(
        /** @var $type 'bank_cz'|'card_payment'|'cod'|'cash'|'bank_sk' */
        public string  $type,
        public string  $name,
        public string  $description,
        public string  $priceVatCzk,
        public string  $priceVatEur,
        public ?string $bankNumber,
        public ?string $bankAccount,
        public ?string $bankName,
        public ?string $bankIban,
    ) {}
}