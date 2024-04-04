<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\CategoryInformationBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryInformationBlock>
 *
 * @method CategoryInformationBlock|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryInformationBlock|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryInformationBlock[]    findAll()
 * @method CategoryInformationBlock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryInformationBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryInformationBlock::class);
    }

//    /**
//     * @return CategoryInformationBlock[] Returns an array of CategoryInformationBlock objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?CategoryInformationBlock
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
