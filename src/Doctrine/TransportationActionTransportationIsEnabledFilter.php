<?php

namespace Greendot\EshopBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * @deprecated will be deleted in future releases, unregister it in tenant app - config/packages/doctrine.yaml
 * No-op: the TransportationAction entity it filtered no longer exists (TransportationAction is an enum now).
 */
class TransportationActionTransportationIsEnabledFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        return '';
    }
}
