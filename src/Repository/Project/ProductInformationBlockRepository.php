<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\ProductInformationBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductInformationBlock>
 *
 * @method ProductInformationBlock|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductInformationBlock|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductInformationBlock[]    findAll()
 * @method ProductInformationBlock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductInformationBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductInformationBlock::class);
    }

//    /**
//     * @return ProductInformationBlock[] Returns an array of ProductInformationBlock objects
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

//    public function findOneBySomeField($value): ?ProductInformationBlock
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
