<?php

namespace Greendot\EshopBundle\Invoice\Data;

class InvoicePaymentData
{
    public function __construct(
        public string       $name,        
        public float        $price,
        public float        $priceSecondary,       
        public ?string      $bankAccount,
        public ?string      $iban,
    ) { }
}