<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderTransportationData
{
    public const PICKUP_TYPE = 'pickup';
    public const DELIVERY_TYPE = 'delivery';

    public function __construct(
        /** @var $type 'pickup'|'delivery' */
        public string $type,
        public string $name,
        public string $description,
        public string $priceVatCzk,
        public string $priceVatEur,
    ) {}
}