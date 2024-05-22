<?php

namespace Greendot\EshopBundle\Repository\Project;

use App\Entity\Project\ProductParamGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductParamGroup>
 *
 * @method ProductParamGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductParamGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductParamGroup[]    findAll()
 * @method ProductParamGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductParamGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductParamGroup::class);
    }

//    /**
//     * @return ProductParamGroup[] Returns an array of ProductParamGroup objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ProductParamGroup
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
