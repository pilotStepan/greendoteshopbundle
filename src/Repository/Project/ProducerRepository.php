<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Producer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Producer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Producer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Producer[]    findAll()
 * @method Producer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProducerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Producer::class);
    }

    public function findByCategory(QueryBuilder $queryBuilder, int|array $categories): QueryBuilder
    {
        $alias = $queryBuilder->getAllAliases()[0];

        $qb = $queryBuilder
            ->join($alias . '.Product', 'prod')
            ->join('prod.categoryProducts', 'cp');

        if (!is_array($categories)) {
            $categories = [$categories];
        }

        $qb->where('cp.category in (:val)')
            ->setParameter('val', $categories);

        return $qb;
    }

    /**
     * @param Category $category
     * @param bool $onlyActive
     * @return Producer[]
     */
    public function getProducersForCategory(Category $category, bool $onlyActive): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb = $this->findByCategory($qb, [$category->getId()]);

        if ($onlyActive) {
            $qb = $qb->andWhere('prod.isActive = true');
        }

        return $qb->getQuery()->getResult();

    }
}
