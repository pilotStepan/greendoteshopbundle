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

        // PurchaseProductVariant pricing (order-specific, possibly custom prices) goes through
        // create() unchanged — the prefetch shape below only covers plain ProductVariant pricing.
        if ($productVariant instanceof ProductVariant) {
            $allPrices = $this->priceRepository->findPricesByDateAndProductVariantNew($productVariant, new \DateTime(), null);

            return $this->productVariantPriceFactory->createFromPrefetchedPrices(
                pv: $productVariant,
                prefetchedPrices: $allPrices,
                currencyOrConversionRate: $currency,
                amount: $amount,
                vatCalculationType: $vatCalculationType,
                discountCalculationType: $discountCalculationType,
                parentProduct: $parentProduct,
            );
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
        // One fetch of every valid price row for this variant (grouped by minimalAmount, DESC —
        // same shape findPricesByDateAndProductVariantNew() always returns), reused for every
        // tier below instead of each tier re-querying getMinimalAmount() +
        // findPricesByDateAndProductVariantNew() from scratch.
        $allPrices = $this->priceRepository->findPricesByDateAndProductVariantNew($productVariant, new \DateTime(), null);

        $array = [];
        foreach (array_keys($allPrices) as $minimalAmount) {
            // ProductVariantPrice does its own ceiling filtering against the full map — see its
            // $prefetchedPrices constructor doc.
            $array[$minimalAmount] = $this->productVariantPriceFactory->createFromPrefetchedPrices(
                pv: $productVariant,
                prefetchedPrices: $allPrices,
                currencyOrConversionRate: $currency,
                amount: $minimalAmount,
                vatCalculationType: $vatCalculationType,
                discountCalculationType: $discountCalculationType,
            );
        }
        return $array;
    }
}