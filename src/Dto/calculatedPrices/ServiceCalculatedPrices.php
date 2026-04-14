<?php

namespace Greendot\EshopBundle\Dto\calculatedPrices;

final class ServiceCalculatedPrices
{
    public function __construct(
        public ?float $priceVat = null,
        public ?float $priceNoVat = null,
    ){}
}