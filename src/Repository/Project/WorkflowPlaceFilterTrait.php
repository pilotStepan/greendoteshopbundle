<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\ORM\QueryBuilder;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

trait WorkflowPlaceFilterTrait
{
    private function addPlaceFilter(QueryBuilder $qb, string $alias, PWC|string $place): QueryBuilder
    {
        $place = $place instanceof PWC ? $place->value : $place;
        return $qb
            ->andWhere(sprintf('%s.marking LIKE :place_%s', $alias, $place))
            ->setParameter('place_' . $place, sprintf('%%"%s"%%', $place))
        ;
    }

    private function excludePlaces(QueryBuilder $qb, string $alias, PWC|string ...$places): QueryBuilder
    {
        foreach ($places as $place) {
            $place = $place instanceof PWC ? $place->value : $place;
            $qb
                ->andWhere(sprintf('(%1$s.marking NOT LIKE :exclude_%2$s OR %1$s.marking IS NULL)', $alias, $place))
                ->setParameter('exclude_' . $place, sprintf('%%"%s"%%', $place))
            ;
        }
        return $qb;
    }
}