<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderPaymentData
{
    /** @var array<int, string> */
    public const TYPES = [
        1 => 'bank_cz',
        2 => 'card_payment',
        3 => 'cod',
        4 => 'cash',
        5 => 'bank_sk',
    ];

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