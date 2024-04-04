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

    // /**
    //  * @return CategoryCategory[] Returns an array of CategoryCategory objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CategoryCategory
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function findSubMenuCategories($super_category_id)
    {
        return $this->createQueryBuilder('cc')
            ->andWhere('cc.category_super = :id')
            ->setParameter('id', $super_category_id)
            ->orderBy('cc.sequence', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

    public function findSuperMenuCategory($sub_category_id)
    {
        return $this->createQueryBuilder('cc')
            ->andWhere('cc.category_sub = :id')
            ->setParameter('id', $sub_category_id)
            ->orderBy('cc.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

    public function findSuperCategories($super_category_id)
    {
        return $this->createQueryBuilder('cc')
            ->andWhere('cc.category_sub = :id')
            ->setParameter('id', $super_category_id)
            ->orderBy('cc.sequence', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

    public function getMaxSuperCategorySequence($super_category_id)
    {
        return $this->createQueryBuilder('cc')
            ->select('MAX(cc.sequence) AS max_sequence')
            ->andWhere('cc.category_super = :id')
            ->setParameter('id', $super_category_id)
            ->getQuery()
            ->getResult()
            ;
    }

    public function findConnectionsByCategorySub($categorySub)
    {
        return $this->createQueryBuilder('cc')
            ->andWhere('cc.category_sub = :id')
            ->setParameter('id', $categorySub)
            ->orderBy('cc.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
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
