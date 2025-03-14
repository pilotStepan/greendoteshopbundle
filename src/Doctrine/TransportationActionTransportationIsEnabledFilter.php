<?php

namespace Greendot\EshopBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Greendot\EshopBundle\Entity\Project\Comment;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\TransportationAction;

class TransportationActionTransportationIsEnabledFilter extends SQLFilter
{

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // check if transportation action
        if ($targetEntity->getReflectionClass()->name !== TransportationAction::class)
        {
            return "";
        }

        // filter
        return sprintf(
            'EXISTS (SELECT 1 FROM transportation t WHERE t.action_id = %s.id AND t.is_enabled = 1)',
            $targetTableAlias
        );
    }
}