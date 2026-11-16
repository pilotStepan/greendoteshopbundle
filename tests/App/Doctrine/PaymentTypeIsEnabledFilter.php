<?php

namespace Greendot\EshopBundle\Tests\App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Greendot\EshopBundle\Entity\Project\PaymentType;

/**
 * A tenant's own visibility filter, as some tenants register one next to the bundle's.
 */
class PaymentTypeIsEnabledFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->getReflectionClass()->name !== PaymentType::class) {
            return '';
        }

        return sprintf('%s.is_enabled = true', $targetTableAlias);
    }
}
