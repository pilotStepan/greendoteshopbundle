<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderTransportationData
{
    public function __construct(
        public string $action,
        public string $country,
        public string $name,
        public string $description,
        public string $priceCzk,
        public string $priceEur,
    ) {}
}