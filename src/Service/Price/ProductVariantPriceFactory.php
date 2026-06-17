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
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\DiscountCombinationStrategyInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

class ProductVariantPriceFactory
{
    private int $afterRegistrationBonus;

    public function __construct(
        private Security                    $security,
        private PriceRepository             $priceRepository,
        private DiscountService             $discountService,
        private PriceUtils                  $priceUtils,
        private SettingsRepository          $settingsRepository,
        private ProductProductRepository    $productProductRepository,
        #[AutowireLocator('greendot_eshop.discount_combination_strategy', indexAttribute: 'key')]
        private ContainerInterface          $discountCombinationStrategies,
        #[Autowire(param: 'greendot_eshop.shop.price.extension.discount_combination_strategy')]
        private string                      $discountCombinationStrategyKey,
    ) {
        $this->afterRegistrationBonus = $this->settingsRepository->findParameterValueWithName('after_registration_discount') ?? 0;
    }

    private function discountCombinationStrategy(): DiscountCombinationStrategyInterface
    {
        if (!$this->discountCombinationStrategies->has($this->discountCombinationStrategyKey)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown discount combination strategy "%s". No service tagged "greendot_eshop.discount_combination_strategy" with key "%s" found.',
                $this->discountCombinationStrategyKey,
                $this->discountCombinationStrategyKey,
            ));
        }
        return $this->discountCombinationStrategies->get($this->discountCombinationStrategyKey);
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

        $productVariantPrice = new ProductVariantPrice(
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
            $this->productProductRepository,
            $this->discountCombinationStrategy(),
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
            $this->discountCombinationStrategy(),
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

    public function entityLoadFromContext(Price $price, $context) : ?ProductVariantPrice
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

