<?php

namespace Greendot\EshopBundle\Repository\Utils;

use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

trait SafeJoin
{
    /**
     * Adds a join to the Doctrine QueryBuilder only if it hasn't already been added.
     *
     * This prevents duplicate alias errors in modular code where multiple functions
     * may request the same join.
     *
     * @param QueryBuilder $qb        The Doctrine QueryBuilder instance to modify.
     * @param string       $rootAlias The alias of the root entity (e.g., 'e' for 'FROM Entity e').
     * @param string       $path      The relation path from the root entity (e.g., 'joinedEntity').
     * @param string       $alias     The alias to assign to the joined entity (e.g., 'j').
     * @param string       $joinType  The type of join to perform: 'left' (default) or 'inner'.
     *
     * @throws InvalidArgumentException If an unsupported join type is provided.
     *
     * @example
     * safeJoin($qb, 'e', 'category', 'c');
     */
    function safeJoin(QueryBuilder $qb, string $rootAlias, string $path, string $alias, string $joinType = 'left'): void
    {

        $joinDqlParts = $qb->getDQLParts()['join'];
        foreach ($joinDqlParts as $joins) {
            foreach ($joins as $join) {
                if ($join->getAlias() === $alias) {
                    return;
                }
            }
        }



        if ($joinType === 'left') {
            $qb->leftJoin("$rootAlias.$path", $alias);
        } elseif ($joinType === 'inner') {
            $qb->innerJoin("$rootAlias.$path", $alias);
        } else {
            throw new \InvalidArgumentException("Unsupported join type: $joinType");
        }
    }

}