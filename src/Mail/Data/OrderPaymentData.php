<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderPaymentData
{
    public const BANK_CZ_TYPE = 'bank_cz';
    public const CARD_PAYMENT_TYPE = 'card_payment';
    public const COD_TYPE = 'cod';
    public const CASH_TYPE = 'cash';
    public const BANK_SK_TYPE = 'bank_sk';

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