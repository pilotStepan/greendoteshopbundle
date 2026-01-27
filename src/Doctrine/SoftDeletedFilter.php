<?php

namespace Greendot\EshopBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Greendot\EshopBundle\Entity\Interface\SoftDeletedInterface;
use Greendot\EshopBundle\Entity\Project\Currency;

class SoftDeletedFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (
            !$targetEntity->getReflectionClass()->implementsInterface(
                SoftDeletedInterface::class
            )
        ) {
            return '';
        }


        return sprintf('%s.is_deleted = false', $targetTableAlias);
    }
}
