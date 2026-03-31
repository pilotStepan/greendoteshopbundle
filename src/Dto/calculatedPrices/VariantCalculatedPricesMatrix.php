<?php

namespace Greendot\EshopBundle\Dto\calculatedPrices;


final class VariantCalculatedPricesMatrix
{
    public function __construct(
        public ?float $priceVat,
        public ?float $priceNoVat,
        public ?float $priceVatNoDiscount,
        public ?float $priceNoVatNoDiscount,
    )
    { }
}