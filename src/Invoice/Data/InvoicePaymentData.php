<?php

namespace Greendot\EshopBundle\Invoice\Data;

use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;

class InvoicePaymentData
{
    public function __construct(
        public string                   $name,        
        public float                    $price,
        public float                    $priceSecondary,  
        public float                    $priceNoVat,
        public float                    $priceNoVatSecondary,     
        public ?string                  $bankAccount,
        public ?string                  $iban,
        public ?PaymentTypeActionGroup  $actionGroup,
    ) { }
}