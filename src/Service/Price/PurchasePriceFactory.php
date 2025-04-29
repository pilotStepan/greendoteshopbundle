<?php

namespace App\Service\Price;

use App\Entity\Project\Currency;
use App\Entity\Project\Purchase;
use App\Enum\DiscountCalculationType;
use App\Enum\VatCalculationType;
use App\Repository\Project\CurrencyRepository;

class PurchasePriceFactory
{
    public function __construct(
        private ProductVariantPriceFactory  $productVariantPriceFactory,
        private readonly CurrencyRepository $currencyRepository,
        private readonly PriceUtils         $priceUtils
    )
    {
    }

    public function create(
        Purchase                $purchase,
        Currency                $currency,
        VatCalculationType      $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount
    ): PurchasePrice
    {
        return new PurchasePrice(
            $purchase,
            $vatCalculationType,
            $discountCalculationType,
            $currency,
            $this->productVariantPriceFactory,
            $this->currencyRepository,
            $this->priceUtils
        );
    }

}