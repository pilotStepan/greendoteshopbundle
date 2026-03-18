<?php

namespace Greendot\EshopBundle\Dto\calculatedPrices;


final class PurchaseCalculatedPricesMatrix
{
    public function __construct(
        public int $priceVat,
        public int $priceNoVat,
        public int $priceVatNoDiscount,
        public int $priceNoVatNoDiscount,
        public int $priceVatNoServices,
        public int $priceNoVatNoServices,
        public int $priceVatNoDiscountNoServices,
        public int $priceNoVatNoDiscountNoServices,
    )
    { }
}