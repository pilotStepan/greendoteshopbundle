<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderTransportationData
{
    public function __construct(
        public string $name,
        public string $description,
        public string $priceVatCzk,
        public string $priceVatEur,
    ) {}
}