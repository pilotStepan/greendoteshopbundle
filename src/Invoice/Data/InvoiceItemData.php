<?php

namespace Greendot\EshopBundle\Invoice\Data;

class InvoiceItemData
{
    public function __construct(
        public string   $name,
        public int      $amount,
        public string   $externalId,
        public float    $priceNoVat,
        public float    $priceNoVatSecondary,
        public float    $vatPercentage,
        public float    $priceVat,
        public float    $priceVatSecondary,
    ) { }
}