<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;

class DataLayerItemFactory
{
    use FactoryUtilsTrait;

    public function __construct(
        private readonly ProductVariantPriceFactory $productVariantPriceFactory,
        private readonly CurrencyManager $currencyManager
    ){}

    public function createFromVariant(
        ProductVariant $variant,
        ?Currency $currency,
        ?int            $quantity = null,
        ?string        $item_variant = null,
        ?array         $parameters = null,
        ?int           $index = null,
    ): DataLayerItem {
        $product = $variant->getProduct();
        if (!$currency){
            $currency = $this->currencyManager->get();
        }

        $productVariantPrice = $this->productVariantPriceFactory->create($variant,$currency, $quantity);
        if (!$quantity){
            $quantity = $productVariantPrice->getMinimalAmount() ?? 1;
        }
        $priceNoVat = $productVariantPrice->getPrice();
        $priceVat = $productVariantPrice->setVatCalculationType(VatCalculationType::WithVAT)->getPrice();

        return new DataLayerItem(
            item_id: $variant->getId(),
            item_name: $product->getName(),
            quantity: $quantity,
            priceVat: $priceVat,
            priceNoVat: $priceNoVat,
            item_brand: $product->getProducer()?->getName() ?? 'Unknown',
            categories: $this->buildCategories($product),
            item_variant: $item_variant,
            parameters: $parameters,
            index: $index,
        );
    }

    public function createFromProduct(
        Product $product,
        float   $priceVat,
        float   $priceNoVat,
        int     $quantity = 1,
        ?int    $index = null,
    ): DataLayerItem {
        return new DataLayerItem(
            item_id: $product->getId(),
            item_name: $product->getName(),
            quantity: $quantity,
            priceVat: $priceVat,
            priceNoVat: $priceNoVat,
            item_brand: $product->getProducer()?->getName() ?? 'Unknown',
            categories: $this->buildCategories($product),
            index: $index,
        );
    }

    private function buildCategories(Product $product): array
    {
        $category = $product->getCategoryProducts()?->first()?->getCategory();
        if (!$category) {
            return [];
        }
        return [$this->getCategoryNameTreeUp($category)];
    }
}