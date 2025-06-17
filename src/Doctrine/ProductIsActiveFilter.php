<?php

namespace Greendot\EshopBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Greendot\EshopBundle\Entity\Project\Product;

class ProductIsActiveFilter extends SQLFilter
{

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // check if product
        if ($targetEntity->getReflectionClass()->name !== Product::class)
        {
            return "";
        }

        // filter
        return sprintf('%s.is_active = true', $targetTableAlias);
    }
}