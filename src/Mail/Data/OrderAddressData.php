<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderAddressData
{
    public function __construct(
        public string $fullName,
        public string $street,
        public string $city,
        public string $zip,
        public ?string $company = null,
        public ?string $ic = null,
        public ?string $dic = null,
    ) {}
}