<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\ViewItem\ViewItem;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Service\CurrencyManager;

class ViewItemFactory
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

    public function create(ProductVariant $productVariant): ViewItem
    {
        $items[] = $this->dataLayerItemFactory->createFromVariant(
            variant: $productVariant,
            currency: $this->currency,
            item_variant: $this->getVariantNameSafe($productVariant),
            parameters: $this->getFormatedParameters($productVariant),
        );


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

        return new ViewItem(
            currency: $this->currency->getName(),
            priceVat: $lowestPriceItem->priceVat,
            priceNoVat: $lowestPriceItem->priceNoVat,
            valueVat: $lowestPriceItem->priceVat,
            valueNoVat: $lowestPriceItem->priceNoVat,
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
        $label = $this->parameterRepository->getVariantParametersLabel($productVariant, ' ');

        return $label !== '' ? $label : $productVariant->getName();
    }
}