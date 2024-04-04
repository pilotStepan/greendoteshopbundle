<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PurchaseProductVariant|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseProductVariant|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseProductVariant[]    findAll()
 * @method PurchaseProductVariant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseProductVariant::class);
    }

    // /**
    //  * @return OrderProductVariant[] Returns an array of OrderProductVariant objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?OrderProductVariant
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
