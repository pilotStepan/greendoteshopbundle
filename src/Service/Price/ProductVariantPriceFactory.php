<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Greendot\EshopBundle\Service\DiscountService;
use Symfony\Bundle\SecurityBundle\Security;

readonly class ProductVariantPriceFactory
{
    public function __construct(
        private Security        $security,
        private PriceRepository $priceRepository,
        private DiscountService $discountService,
        private PriceUtils      $priceUtils,
        private SettingsRepository $settingsRepository
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
            $this->settingsRepository,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils
        );
    }
}