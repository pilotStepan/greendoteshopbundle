<?php

namespace Greendot\EshopBundle\Repository\Project;

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

    public function findByCategory(QueryBuilder $queryBuilder, int $category_id): QueryBuilder
    {
        $alias = $queryBuilder->getAllAliases()[0];

        return $queryBuilder->join($alias.'.Product', 'prod')
            ->join('prod.categoryProducts', 'cp')
            ->where('cp.category=:val')
            ->setParameter('val', $category_id);
    }
}
