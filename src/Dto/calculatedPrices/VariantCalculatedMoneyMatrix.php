<?php

namespace Greendot\EshopBundle\Dto\calculatedPrices;

use Greendot\EshopBundle\Money\Money;

/**
 * Money-typed twin of VariantCalculatedPricesMatrix's price fields (the two percentage
 * fields have no currency, so they stay float-only on VariantCalculatedPricesMatrix).
 */
final class VariantCalculatedMoneyMatrix
{
    public function __construct(
        public Money $priceVat,
        public Money $priceNoVat,
        public Money $priceVatNoDiscount,
        public Money $priceNoVatNoDiscount,
        public Money $totalPriceVat,
        public Money $totalPriceNoVat,
        public Money $totalPriceVatNoDiscount,
        public Money $totalPriceNoVatNoDiscount,
    ) {}
}
