<?php

namespace Greendot\EshopBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Greendot\EshopBundle\Entity\Project\Transportation;

class TransportationIsEnabledFilter extends SQLFilter
{

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // check if transportation
        if ($targetEntity->name !== Transportation::class)
        {
            return "";
        }

        // filter
        return sprintf('%s.is_enabled = true', $targetTableAlias);
    }
}