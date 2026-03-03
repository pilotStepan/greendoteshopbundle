<?php

namespace Greendot\EshopBundle\StructuredData\Provider\Default;

use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\StructuredData\Contract\StructuredDataProviderInterface;
use Greendot\EshopBundle\StructuredData\Model\Brand;
use Greendot\EshopBundle\StructuredData\Model\Offer;
use Greendot\EshopBundle\StructuredData\Model\Product as ProductModel;

/**
 * Default provider for Product entities.
 */
class DefaultProductProvider implements StructuredDataProviderInterface
{
    public function supports(?object $object): bool
    {
        return $object instanceof ProductEntity;
    }

    /**
     * @param ProductEntity|null $object
     * @return ProductModel|null
     */
    public function provide(?object $object): ?ProductModel
    {
        if (!$object) {
            return null;
        }

        $model = new ProductModel();
        $model->setName($object->getName());
        $model->setDescription($object->getDescription() ?? $object->getMetaDescription());
        
        if ($object->getProducer()) {
            $brand = new Brand();
            $brand->setName($object->getProducer()->getName());
            $model->setBrand($brand);
        }

        if ($object->getUpload()) {
            $model->setImage($object->getUpload()->getPath());
        }

        $variants = $object->getProductVariants();
        if ($variants && count($variants) > 0) {
            $offers = [];
            foreach ($variants as $variant) {
                /** @var ProductVariant $variant */
                $offer = new Offer();
                
                // Use calculated prices if available
                $prices = $variant->getCalculatedPrices();
                if (!empty($prices) && isset($prices["1"])) {
                    $offer->setPrice($prices["1"]['priceVat']);
                }
                
                $offer->setAvailability($variant->getStock() > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock');
                $offers[] = $offer;
            }
            
            if (count($offers) === 1) {
                $model->setOffers($offers[0]);
                $model->setSku($variants->first()->getExternalId());
            } else {
                $model->setOffers($offers);
            }
        }

        return $model;
    }

    public function getPriority(): int
    {
        return 0; // Default priority
    }
}
