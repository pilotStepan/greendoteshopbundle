<?php

namespace Greendot\EshopBundle\StructuredData\Provider\Default;

use Greendot\EshopBundle\StructuredData\Model\ItemList;
use Greendot\EshopBundle\StructuredData\Model\ListItem;
use Greendot\EshopBundle\Entity\Project\CategoryProduct;
use Greendot\EshopBundle\StructuredData\Model\AggregateOffer;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Greendot\EshopBundle\StructuredData\Model\Product as ProductModel;
use Greendot\EshopBundle\StructuredData\Contract\StructuredDataProviderInterface;

/**
 * Default provider for Category entities.
 */
class DefaultCategoryProvider implements StructuredDataProviderInterface
{
    public function supports(mixed $object): bool
    {
        return $object instanceof CategoryEntity;
    }

    public function provide(mixed $object): object|array|null
    {
        if (!$object) {
            return null;
        }

        $list = new ItemList();
        $list->setName($object->getName());

        $elements = [];
        $position = 1;

        // Only take top 20 products for listing to avoid massive JSON-LD
        $categoryProducts = $object->getCategoryProducts();
        $count = 0;
        foreach ($categoryProducts as $cp) {
            if ($count >= 20) break;
            /** @var CategoryProduct $cp */
            $productEntity = $cp->getProduct();
            if ($productEntity && $productEntity->getIsActive()) {
                $item = new ListItem();
                $item->setPosition($position++);
                $item->setName($productEntity->getName());

                // Create a simplified product model for the listing
                $productModel = new ProductModel();
                $productModel->setName($productEntity->getName());

                // Set price ranges if variants exist
                $variants = $productEntity->getProductVariants();
                if ($variants && count($variants) > 0) {
                    $prices = [];
                    foreach ($variants as $v) {
                        $p = $v->getCalculatedPrices();
                        if (isset($p["1"])) {
                            $prices[] = $p["1"]["priceVat"];
                        }
                    }

                    if (!empty($prices)) {
                        $aggOffer = new AggregateOffer();
                        $aggOffer->setLowPrice(min($prices));
                        $aggOffer->setHighPrice(max($prices));
                        $aggOffer->setOfferCount(count($prices));
                        $productModel->setOffers($aggOffer);
                    }
                }

                $item->setItem($productModel);
                $elements[] = $item;
                $count++;
            }
        }

        $list->setItemListElement($elements);

        return $list;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
