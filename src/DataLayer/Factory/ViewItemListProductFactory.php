<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\ViewItemListProduct\ViewItemListProduct;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Service\CurrencyManager;

class ViewItemListProductFactory
{
    private Currency $currency;

    public function __construct(
        private readonly ParameterRepository        $parameterRepository,
        private readonly DataLayerItemFactory       $dataLayerItemFactory,
        CurrencyManager                             $currencyManager,
    )
    {
        $this->currency = $currencyManager->get();
    }

    public function create(Product $product): ViewItemListProduct
    {
        $items = [];

        $valueVat = 0;
        $valueNoVat = 0;

        foreach ($product->getProductVariants() as $variant) {
            $item = $this->dataLayerItemFactory->createFromVariant(
                variant: $variant,
                currency: $this->currency,
                item_variant: $this->getVariantNameSafe($variant),
                parameters: $this->getFormatedParameters($variant),
            );
            $valueNoVat += $item->priceNoVat;
            $valueVat += $item->priceVat;

            $items[] = $item;
        }

        $lowestPriceItem = null;
        foreach ($items as $item) {
            if ($lowestPriceItem === null) {
                $lowestPriceItem = $item;
                continue;
            }

            if ($lowestPriceItem->priceNoVat == 0) {
                $lowestPriceItem = $item;
                continue;
            }

            if ($item->priceNoVat > 0 && $item->priceNoVat < $lowestPriceItem->priceNoVat) {
                $lowestPriceItem = $item;
            }
        }

        return new ViewItemListProduct(
            currency: $this->currency->getName(),
            priceVat: $lowestPriceItem?->priceVat ?? 0.0,
            priceNoVat: $lowestPriceItem?->priceNoVat ?? 0.0,
            valueVat: $valueVat,
            valueNoVat: $valueNoVat,
            items: $items
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