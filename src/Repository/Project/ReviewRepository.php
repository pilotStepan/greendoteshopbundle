<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function findByProductQB(int $productId, QueryBuilder $queryBuilder): QueryBuilder
    {
        $alias = $queryBuilder->getRootAliases()[0];

        return $queryBuilder
            ->andWhere(sprintf('%s.Product = :productId', $alias))
            ->setParameter('productId', $productId);
    }

    /**
     * Compute review distribution and average on *any* filtered QB.
     *
     * @param QueryBuilder $queryBuilder filtered, unpaginated QB (root alias must be "r")
     * @return array{distribution: array<int,int>, avg: float}
     */
    public function getStats(QueryBuilder $queryBuilder): array
    {
        // Clone & reset (select, join, group, etc.) so we don’t pollute the original QB or inherit unwanted clauses
        $distQB = clone $queryBuilder;
        $distQB
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->resetDQLPart('join')
            ->select('r.stars AS stars', 'COUNT(DISTINCT r.id) AS total')
            ->groupBy('r.stars');

        $rows = $distQB->getQuery()->getScalarResult();

        $distribution = array_fill(1, 5, 0);
        foreach ($rows as $row) {
            $s = (int)$row['stars'];
            if ($s >= 1 && $s <= 5) {
                $distribution[$s] = (int)$row['total'];
            }
        }
        $distribution = array_reverse($distribution, true);

        // For the average, sum/count in PHP, so we can round consistently (and avoid any DB-specific AVG quirks)
        $avgQB = clone $queryBuilder;
        $avgQB
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->resetDQLPart('groupBy')
            ->resetDQLPart('having')
            ->resetDQLPart('join')
            ->select(
                'COUNT(DISTINCT r.id) AS cnt',
                'SUM(r.stars) AS sumStars'
            );

        $result = $avgQB->getQuery()->getScalarResult()[0];
        $count = (int)$result['cnt'];
        $sumStars = (float)$result['sumStars'];
        $avgRating = $count > 0
            ? round($sumStars / $count, 2)
            : 0.0;

        return [
            'distribution' => $distribution,
            'avgRating' => $avgRating,
        ];
    }
}
