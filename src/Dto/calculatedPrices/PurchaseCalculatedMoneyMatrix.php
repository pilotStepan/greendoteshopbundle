<?php

namespace Greendot\EshopBundle\Dto\calculatedPrices;

use Greendot\EshopBundle\Money\Money;

/**
 * Money-typed twin of PurchaseCalculatedPricesMatrix - same 8 combinations of
 * VAT/discount/services, each value tagged with its currency.
 */
final class PurchaseCalculatedMoneyMatrix
{
    public function __construct(
        public Money $priceVat,
        public Money $priceNoVat,
        public Money $priceVatNoDiscount,
        public Money $priceNoVatNoDiscount,
        public Money $priceVatNoServices,
        public Money $priceNoVatNoServices,
        public Money $priceVatNoDiscountNoServices,
        public Money $priceNoVatNoDiscountNoServices,
    ) {}
}
