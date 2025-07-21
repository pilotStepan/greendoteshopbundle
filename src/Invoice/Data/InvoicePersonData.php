<?php

namespace Greendot\EshopBundle\Invoice\Data;

class InvoicePersonData 
{
    public function __construct(
        public ?string      $company,
        public ?string      $name,
        public ?string      $street,
        public ?string      $zip,
        public ?string      $city,
        public ?string      $country,
        public ?string      $ic,
        public ?string      $dic,
        public ?string      $phone,
        public ?string      $email,
    ) { }
}