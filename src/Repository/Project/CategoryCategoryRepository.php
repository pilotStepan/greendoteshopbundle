<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\CategoryCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CategoryCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryCategory[]    findAll()
 * @method CategoryCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryCategory::class);
    }

    public function findConnectionsByCategorySuper($categorySuper){
        return $this->createQueryBuilder('cc')
            ->andWhere('cc.category_super = :id')
            ->setParameter('id', $categorySuper)
            ->orderBy('cc.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }
}
