<?php

namespace Greendot\EshopBundle\Dto;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductProduct;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\DiscountCalculationType;

final class ProductVariantPriceContext
{
    public function __construct(
        public Currency|ConversionRate               $currencyOrConversionRate,
        public ?int                                  $amount = null,
        public VatCalculationType                    $vatCalculationType = VatCalculationType::WithVAT,
        public DiscountCalculationType               $discountCalculationType = DiscountCalculationType::WithDiscount,
        public Product|ProductProduct|null           $parentProduct = null
    ) { }
}