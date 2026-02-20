<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\Entity\Project\Category;

trait FactoryUtilsTrait
{
    /**
     * @param Category $category
     * @param bool $includeSelf
     * @return Category[]
     */
    public function getCategoryNameTreeUp(Category $category, bool $includeSelf = true): array
    {
        $parents = [];
        // Get the relation that points "upwards"
        $currentRelations = $category->getCategorySubCategories();

        while (!$currentRelations->isEmpty()) {
            $relation = $currentRelations->first();
            $parent = $relation->getCategorySuper();

            if ($parent) {
                if ($parent->getId() !== $category->getId() || $includeSelf){
                    $parents[] = $parent->getName();
                }
                // Move up to the next level
                $currentRelations = $parent->getCategorySubCategories();
            } else {
                break;
            }
        }

        return $parents;
    }

    public function getFromCalculatedPricesSafe(array $calculatedPrices, string $key): float
    {
        if (isset($calculatedPrices[$key])) {
            return $calculatedPrices[$key];
        }
        return 0;
    }
}