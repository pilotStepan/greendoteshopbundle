<?php

namespace Greendot\EshopBundle\Dto\calculatedPrices;


final class VariantCalculatedPricesMatrix
{
    public function __construct(
        public ?float $priceVat,
        public ?float $priceNoVat,
        public ?float $priceVatNoDiscount,
        public ?float $priceNoVatNoDiscount,
        public ?float $totalPriceVat,
        public ?float $totalPriceNoVat,
        public ?float $totalPriceVatNoDiscount,
        public ?float $totalPriceNoVatNoDiscount,
        public ?float $discountPercentage,
        public ?float $productDiscountPercentage,
    )
    { }
}