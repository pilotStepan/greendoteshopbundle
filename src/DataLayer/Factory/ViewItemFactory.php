<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\ViewItem\ViewItem;
use Greendot\EshopBundle\DataLayer\Data\ViewItem\ViewItemItem;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Service\CurrencyManager;

class ViewItemFactory
{
    use FactoryUtilsTrait;

    public function __construct(
        private readonly CurrencyManager          $currencyManager,
        private readonly ParameterRepository      $parameterRepository
    )
    {
    }

    protected array $sharedData = [];

    public function create(Product $product, ?array $selectedVariants = null): ViewItem
    {
        $currency = $this->currencyManager->get();

        $categories = [];
        $productCategory = $product?->getCategoryProducts()?->first()?->getCategory();
        if ($productCategory) {
            $categories[] = $this->getCategoryNameTreeUp($productCategory);
        }
        $this->sharedData['categories'] = $categories;
        $this->sharedData['brand'] = $product?->getProducer()?->getName() ?? 'Unknown';
        $this->sharedData['name'] = $product->getName();

        $items = [];
        foreach ($product->getProductVariants() as $variant) {
            if ($selectedVariants !== null && !in_array($variant->getId(), $selectedVariants)) {
                continue;
            }
            $items[] = $this->createViewItemItem($variant);
        }

        return new ViewItem(
            currency: $currency->getName(),
            priceVat: 0,
            priceNoVat: 0,
            items: $items
        );

    }

    public function createViewItemItem(ProductVariant $productVariant): ViewItemItem
    {
        $calculatedPrices = [];
        $quantity = 1;
        if ($productVariant->getCalculatedPrices()) {
            $calculatedPrices = $productVariant->getCalculatedPrices();
            $quantity = array_keys($calculatedPrices);
            $quantity = min($quantity);
            $calculatedPrices = $calculatedPrices[$quantity];
        }
        return new ViewItemItem(
            item_id: $productVariant->getId(),
            item_name: $this->sharedData['name'],
            item_brand: $this->sharedData['brand'],
            item_variant: $this->getVariantNameSafe($productVariant), // use name as fallback, mainly use isVariant parameterGroups
            priceVat: $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceVat'),
            priceNoVat: $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceNoVat'),
            quantity: $quantity,
            categories: $this->sharedData['categories'],
            parameters: $this->getFormatedParameters($productVariant)
        );
    }

    protected function getFormatedParameters(ProductVariant $productVariant): array
    {
        $parameters = $this->parameterRepository->getFormattedParameters($productVariant, null, ['excludeIsVariant' => true]);
        $formatedParameters = [];
        foreach ($parameters as $parameterGroupName => $parameter) {
            $unit = !empty($parameter['unit']) ? ' ' . $parameter['unit'] : '';

            $formattedValues = array_map(function ($value) use ($unit) {
                return $value . $unit;
            }, $parameter['values']);

            $formatedParameters[] = ['name' => $parameterGroupName, 'values' => $formattedValues];
        }
        return $formatedParameters;
    }

    protected function getVariantNameSafe(ProductVariant $productVariant): string
    {
        $queryBuilder = $this->parameterRepository->createQueryBuilder('parameter');
        $queryBuilder->select("CASE WHEN format.name = 'color' THEN color.name ELSE parameter.data END as data");
        $variantDataQB = $this->parameterRepository->findProductParameterGroupsParametersQB($productVariant, $queryBuilder);
        $results = $variantDataQB->getQuery()->getResult();

        if (!$results) {
            return $productVariant->getName();
        }

        $names = array_column($results, 'data');
        return implode(' ', $names);
    }
}