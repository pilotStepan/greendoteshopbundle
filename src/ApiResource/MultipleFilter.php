<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class MultipleFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
               $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (!$this->isPropertyEnabled($property, $resourceClass) || null === $value) {
            return;
        }

        // Convert value to an array (if it's a comma-separated string)
        $values = is_array($value) ? $value : explode(',', $value);
        if (empty($values)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $paramName = $queryNameGenerator->generateParameterName($property);
        $joinAlias = $queryNameGenerator->generateParameterName($property . '_join');

        // Join the relationship
        $queryBuilder
            ->leftJoin("$alias.$property", $joinAlias)
            ->andWhere("$joinAlias.id IN (:$paramName)")
            ->setParameter($paramName, $values);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'multiple' => [
                'property' => 'multiple',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter results where the property matches any of the given values (OR condition). Multiple values can be comma-separated.',
            ],
        ];
    }
}
