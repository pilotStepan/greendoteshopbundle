<?php

namespace App\Service\Price;

use App\Entity\Project\Currency;
use App\Entity\Project\ProductVariant;
use App\Entity\Project\PurchaseProductVariant;
use App\Enum\DiscountCalculationType;
use App\Enum\VatCalculationType;
use App\Repository\Project\PriceRepository;
use App\Service\DiscountService;
use Symfony\Bundle\SecurityBundle\Security;

class ProductVariantPriceFactory
{
    public function __construct(
        private readonly Security                         $security,
        private readonly PriceRepository                  $priceRepository,
        private readonly DiscountService                  $discountService,
        private readonly PriceUtils                       $priceUtils
    ){}

    public function create(
        ProductVariant|PurchaseProductVariant $pv,
        Currency $currency,
        ?int $amount = null,
        VatCalculationType $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount
    ): ProductVariantPrice
    {
        return new ProductVariantPrice(
            $pv,
            $amount,
            $currency,
            $vatCalculationType,
            $discountCalculationType,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils
        );
    }



}