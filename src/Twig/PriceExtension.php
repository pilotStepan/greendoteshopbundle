<?php

namespace Greendot\EshopBundle\Twig;

use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductProduct;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Twig\Attribute\AsTwigFunction;

class PriceExtension
{
    public function __construct(
        private readonly ProductVariantPriceFactory $productVariantPriceFactory,
        private readonly PurchasePriceFactory       $purchasePriceFactory,
        private readonly CurrencyManager            $currencyManager,
        private readonly PriceRepository            $priceRepository

    )
    {
    }

    #[AsTwigFunction('create_product_variant_price')]
    public function createProductVariantPrice(
        ProductVariant|PurchaseProductVariant $productVariant,
        ?Currency                             $currency = null,
        VatCalculationType                    $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType               $discountCalculationType = DiscountCalculationType::WithDiscount,
        ?int                                  $amount = null,
        Product|ProductProduct|null           $parentProduct = null
    ): ProductVariantPrice
    {
        if (!$currency) {
            $currency = $this->currencyManager->get();
        }

        return $this->productVariantPriceFactory->create(
            pv: $productVariant,
            currencyOrConversionRate: $currency,
            amount: $amount,
            vatCalculationType: $vatCalculationType,
            discountCalculationType: $discountCalculationType,
            parentProduct: $parentProduct
        );
    }

    /**
     * @param ProductVariant $productVariant
     * @param Currency $currency
     * @return ProductVariantPrice[]
     */
    #[AsTwigFunction('price_table_array')]
    public function priceTableArray(
        ProductVariant $productVariant,
        Currency       $currency,
        VatCalculationType $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount
    ): array
    {
        $minimalAmounts = $this->priceRepository->getUniqueMinimalAmounts($productVariant);

        $array = [];
        foreach ($minimalAmounts as $minimalAmount) {
            $array[$minimalAmount] = $this->productVariantPriceFactory->create(
                pv: $productVariant,
                currencyOrConversionRate: $currency,
                amount: $minimalAmount,
                vatCalculationType: $vatCalculationType,
                discountCalculationType: $discountCalculationType,
            );
        }
        return $array;
    }
}