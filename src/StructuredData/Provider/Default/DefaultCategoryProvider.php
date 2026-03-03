<?php

namespace Greendot\EshopBundle\StructuredData\Provider\Default;

use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Greendot\EshopBundle\Entity\Project\CategoryProduct;
use Greendot\EshopBundle\StructuredData\Contract\StructuredDataProviderInterface;
use Greendot\EshopBundle\StructuredData\Model\ItemList;
use Greendot\EshopBundle\StructuredData\Model\ListItem;

/**
 * Default provider for Category entities.
 */
class DefaultCategoryProvider implements StructuredDataProviderInterface
{
    public function supports(?object $object): bool
    {
        return $object instanceof CategoryEntity;
    }

    /**
     * @param CategoryEntity|null $object
     * @return ItemList|null
     */
    public function provide(?object $object): ?ItemList
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
            $product = $cp->getProduct();
            if ($product && $product->getIsActive()) {
                $item = new ListItem();
                $item->setPosition($position++);
                $item->setName($product->getName());
                // In real app, we should provide URL here. 
                // As we are in bundle, we might not have the route name yet.
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
