<?php

namespace Greendot\EshopBundle\Invoice\Data;

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
    ) { }
}