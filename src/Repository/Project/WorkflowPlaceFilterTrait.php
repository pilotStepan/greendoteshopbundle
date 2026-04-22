<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\ORM\QueryBuilder;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

trait WorkflowPlaceFilterTrait
{
    private function addPlaceFilter(QueryBuilder $qb, string $alias, PWC $place): QueryBuilder
    {
        return $qb
            ->andWhere(sprintf('%s.marking LIKE :place_%s', $alias, $place->name))
            ->setParameter('place_' . $place->name, sprintf('%%"%s"%%', $place->value))
        ;
    }

    private function excludePlaces(QueryBuilder $qb, string $alias, PWC ...$places): QueryBuilder
    {
        foreach ($places as $place) {
            $qb
                ->andWhere(sprintf('%s.marking NOT LIKE :exclude_%s', $alias, $place->name))
                ->setParameter('exclude_' . $place->name, sprintf('%%"%s"%%', $place->value))
            ;
        }
        return $qb;
    }
}