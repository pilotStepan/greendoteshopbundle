<?php

namespace App\Schema\Provider;

use App\Enum\ProductViewTypeEnum;
use App\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\StructuredData\Model\Brand;
use Greendot\EshopBundle\StructuredData\Model\Offer;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\StructuredData\Model\Product as ProductModel;
use Greendot\EshopBundle\StructuredData\Model\UnitPriceSpecification;
use Greendot\EshopBundle\StructuredData\Model\ProductGroup as ProductGroupModel;
use Greendot\EshopBundle\StructuredData\Contract\StructuredDataProviderInterface;


class CatalogProductProvider implements SchemaProviderInterface
{
    public function supports(mixed $object): bool
    {
        return $object instanceof ProductEntity
            && $object->getProductViewType()?->getId() === ProductViewTypeEnum::CATALOGUE->value;
    }

    /**
     * @param ProductEntity $object
     */
    public function provide(mixed $object): object|array|null
    {
        $variants = $object->getProductVariants();
        $isGroup = $variants && count($variants) > 1;

        $model = $isGroup ? new ProductGroupModel() : new ProductModel();
        $this->fillBasicData($model, $object);

        if ($isGroup) {
            /** @var ProductGroupModel $model */
            $variantModels = [];
            $varyingProps = [];

            foreach ($variants as $variant) {
                $variantModel = new ProductModel();
                $this->fillVariantData($variantModel, $variant);
                $variantModels[] = $variantModel;

                // Identify what varies
                foreach ($variant->getParameters() as $parameter) {
                    $varyingProps[$parameter->getParameterGroup()->getName()] = true;
                }
            }

            $model->setHasVariant($variantModels);
            $model->setVariesBy(array_keys($varyingProps));
        } else if ($variants && count($variants) === 1) {
            $this->fillVariantData($model, $variants->first());
        }

        return $model;
    }

    private function fillBasicData(ProductModel $model, ProductEntity $entity): void
    {
        $model->setName($entity->getName());
        $model->setDescription($entity->getDescription() ?? $entity->getMetaDescription());

        if ($entity->getProducer()) {
            $brand = new Brand();
            $brand->setName($entity->getProducer()->getName());
            $model->setBrand($brand);
        }

        if ($entity->getUpload()) {
            $model->setImage($entity->getUpload()->getPath());
        }
    }

    private function fillVariantData(ProductModel $model, ProductVariant $variant): void
    {
        $model->setSku($variant->getExternalId());

        $offer = new Offer();
        $prices = $variant->getCalculatedPrices();

        if (!empty($prices) && isset($prices["1"])) {
            $priceData = $prices["1"];
            $offer->setPrice($priceData['priceVat']);

            // Handle Discount
            if (isset($priceData['isDiscount']) && $priceData['isDiscount']) {
                $spec = new UnitPriceSpecification();
                $spec->setPrice($priceData['originalPriceVat']);
                $spec->setPriceType('ListPrice');
                $offer->setPriceSpecification($spec);

                // Set validity if available (example logic)
                if (isset($priceData['discountUntil'])) {
                    $offer->setPriceValidUntil($priceData['discountUntil']);
                }
            }
        }

        $offer->setAvailability($variant->getStock() > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock');
        $model->setOffers($offer);

        // Fill additional properties from parameters
        $props = [];
        foreach ($variant->getParameters() as $parameter) {
            $props[] = [
                '@type' => 'PropertyValue',
                'name' => $parameter->getParameterGroup()->getName(),
                'value' => $parameter->getValue(),
            ];
        }
        if (!empty($props)) {
            $model->setAdditionalProperty($props);
        }
    }

    public function getPriority(): int
    {
        return 0; // Default priority
    }
}
