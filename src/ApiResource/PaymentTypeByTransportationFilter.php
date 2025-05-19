<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class PaymentTypeByTransportationFilter extends AbstractFilter
{
    private const FILTER_LABEL = 'PaymentTypeByTransportation';

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []
    ): void
    {
        if ($property !== self::FILTER_LABEL) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->join(sprintf('%s.transportations', $alias), 't')
            ->andWhere('t.id = :transportation_id')
            ->setParameter('transportation_id', $value);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::FILTER_LABEL => [
                'property'    => null,
                'type'        => 'integer',
                'required'    => false,
                'description' => 'Filters payment types based on the specified transportation ID.',
                'openapi'     => [
                    'description' => 'Filters payment types based on the specified transportation ID.',
                ],
            ]
        ];
    }
}