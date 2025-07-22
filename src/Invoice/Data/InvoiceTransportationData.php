<?php

namespace Greendot\EshopBundle\Invoice\Data;

class InvoiceTransportationData
{
    public function __construct(
        public string   $name,
        public float    $price,
        public float    $priceSecondary,  
    ) { }
}