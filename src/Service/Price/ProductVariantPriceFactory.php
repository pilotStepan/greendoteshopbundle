<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Dto\ProductVariantPriceContext;
use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductProduct;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\ProductProductRepository;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Greendot\EshopBundle\Service\DiscountService;
use Symfony\Bundle\SecurityBundle\Security;

readonly class ProductVariantPriceFactory
{
    private readonly int $afterRegistrationBonus;

    public function __construct(
        private Security           $security,
        private PriceRepository    $priceRepository,
        private DiscountService    $discountService,
        private PriceUtils         $priceUtils,
        private SettingsRepository $settingsRepository,
        private ProductProductRepository $productProductRepository
    )
    {
        $this->afterRegistrationBonus = $this->settingsRepository->findParameterValueWithName('after_registration_discount') ?? 0;
    }

    public function create(
        ProductVariant|PurchaseProductVariant $pv,
        Currency|ConversionRate               $currencyOrConversionRate,
        ?int                                  $amount = null,
        VatCalculationType                    $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType               $discountCalculationType = DiscountCalculationType::WithDiscount,
        Product|ProductProduct|null           $parentProduct = null
    ): ProductVariantPrice
    {
        $purchase = null;
        if ($pv instanceof PurchaseProductVariant and $pv?->getPurchase()) {
            $purchase = $pv->getPurchase();
        }

        if ($purchase) {
            if ($pv->getPurchase()->isVatExempted()) $vatCalculationType = VatCalculationType::WithoutVAT;
        }

        $conversionRate = $currencyOrConversionRate;
        if ($currencyOrConversionRate instanceof Currency){
            $conversionRate = $this->priceUtils->getConversionRate($currencyOrConversionRate, $purchase);
        }

        $productVariantPrice =  new ProductVariantPrice(
            $pv,
            $amount,
            $conversionRate,
            $vatCalculationType,
            $discountCalculationType,
            $this->afterRegistrationBonus,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils,
            $this->productProductRepository
        );
        if ($parentProduct){
            $productVariantPrice->setParentProduct($parentProduct);
        }
        return $productVariantPrice;
    }

    public function entityLoad(
        Price                       $price,
        Currency|ConversionRate     $currencyOrConversionRate,
        VatCalculationType          $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType     $discountCalculationType = DiscountCalculationType::WithDiscount,
        Product|ProductProduct|null  $parentProduct = null,
    ): ProductVariantPrice
    {
        $amount = $price->getMinimalAmount();
        $conversionRate = $currencyOrConversionRate;
        if ($currencyOrConversionRate instanceof Currency){
            $conversionRate = $this->priceUtils->getConversionRate($currencyOrConversionRate);
        }

        $productVariantPrice = new ProductVariantPrice(
            $price->getProductVariant(),
            $amount,
            $conversionRate,
            $vatCalculationType,
            $discountCalculationType,
            $this->afterRegistrationBonus,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils,
            $this->productProductRepository,
            $price
        );

         if ($parentProduct){
            $productVariantPrice->setParentProduct($parentProduct);
        }

        return $productVariantPrice;
    }

    public function createFromContext(
        ProductVariant|PurchaseProductVariant   $pv, 
        ProductVariantPriceContext              $context
    ) : ProductVariantPrice
    {
        return $this->create(
            pv: $pv,
            currencyOrConversionRate: $context->currencyOrConversionRate,
            amount: $context->amount,
            vatCalculationType: $context->vatCalculationType,
            discountCalculationType: $context->discountCalculationType,
            parentProduct: $context->parentProduct,
        );
    }

    public function updateFromContext(
        ProductVariantPrice         $productVariantPrice, 
        ProductVariantPriceContext  $context
    )
    {
        $productVariantPrice->setDiscountCalculationType($context->discountCalculationType);
        $productVariantPrice->setVatCalculationType($context->vatCalculationType);
        $productVariantPrice->setCurrency($context->currencyOrConversionRate);
        
        if($context->amount){
            $productVariantPrice->setAmount($context->amount);
        }

        if ($context->parentProduct) {
            $productVariantPrice->setParentProduct($context->parentProduct);
        } else {
            $productVariantPrice->emptyParentProduct();
        }
    }

    public function entityLoadFromContext(Price $price, $context) : ProductVariantPrice
    {
        return $this->entityLoad(
            price:    $price,
            currencyOrConversionRate:   $context->currencyOrConversionRate,
            vatCalculationType:         $context->vatCalculationType,
            discountCalculationType:    $context->discountCalculationType,
            parentProduct:              $context->parentProduct
        );
    }
}

