<?php

namespace Greendot\EshopBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Greendot\EshopBundle\Entity\Project\ProductProduct;

class ProductProductActiveFilter extends SQLFilter
{

    /**
     * @inheritDoc
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->getReflectionClass()->name !== ProductProduct::class) {
            return '';
        }
      
        return sprintf(
            'EXISTS (SELECT 1 FROM product p_child WHERE p_child.id = %1$s.children_product_id AND p_child.is_active = 1) 
            AND EXISTS (SELECT 1 FROM product p_parent WHERE p_parent.id = %1$s.parent_product_id AND p_parent.is_active = 1)',
            $targetTableAlias
        );
    }
}