<?php

namespace Greendot\EshopBundle\Dto\calculatedPrices;


final class VariantCalculatedPricesMatrix
{
    public function __construct(
        public int $priceVat,
        public int $priceNoVat,
        public int $priceVatNoDiscount,
        public int $priceNoVatNoDiscount,
    )
    { }
}