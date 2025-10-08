<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Price;
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
    private readonly int $afterRegistrationBonus;

    public function __construct(
        private Security        $security,
        private PriceRepository $priceRepository,
        private DiscountService $discountService,
        private PriceUtils      $priceUtils,
        private SettingsRepository $settingsRepository
    ){
        $this->afterRegistrationBonus = $this->settingsRepository->findParameterValueWithName('after_registration_discount') ?? 0;
    }

    public function create(
        ProductVariant|PurchaseProductVariant $pv,
        Currency $currency,
        ?int $amount = null,
        VatCalculationType $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount
    ): ProductVariantPrice
    {
        if ($pv instanceof PurchaseProductVariant and $pv?->getPurchase() and $pv->getPurchase()->isVatExempted()){
            $vatCalculationType = VatCalculationType::WithoutVAT;
        }

        return new ProductVariantPrice(
            $pv,
            $amount,
            $currency,
            $vatCalculationType,
            $discountCalculationType,
            $this->afterRegistrationBonus,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils
        );
    }

    public function entityLoad(
        Price $price,
        Currency $currency,
        ?int $amount = null,
        VatCalculationType $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount
    ): ProductVariantPrice
    {
        if (!$amount){
            $amount = $price->getMinimalAmount();
        }

        return new ProductVariantPrice(
            $price->getProductVariant(),
            $amount,
            $currency,
            $vatCalculationType,
            $discountCalculationType,
            $this->afterRegistrationBonus,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils,
            $price
        );
    }
}