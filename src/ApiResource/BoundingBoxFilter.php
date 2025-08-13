<?php

namespace Greendot\EshopBundle\ApiResource;

use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;

final class BoundingBoxFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($property !== 'bbox') return;

        $coords = explode(',', $value);
        if (count($coords) !== 4) return;

        [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $coords);

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere("$alias.lat >= :minLat")
            ->andWhere("$alias.lat <= :maxLat")
            ->andWhere("$alias.lng >= :minLng")
            ->andWhere("$alias.lng <= :maxLng")
            ->setParameter('minLat', $minLat)
            ->setParameter('maxLat', $maxLat)
            ->setParameter('minLng', $minLng)
            ->setParameter('maxLng', $maxLng)
        ;
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'bbox' => [
                'property' => 'bbox',
                'type' => 'string',
                'required' => false,
                'description' => 'Bounding box filter: minLng,minLat,maxLng,maxLat',
            ],
        ];
    }
}
