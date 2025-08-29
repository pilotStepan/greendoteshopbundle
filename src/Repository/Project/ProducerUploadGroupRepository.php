<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\ProducerUploadGroup;

/**
 * @extends ServiceEntityRepository<ProducerUploadGroup>
 *
 * @method ProducerUploadGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProducerUploadGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProducerUploadGroup[]    findAll()
 * @method ProducerUploadGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProducerUploadGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProducerUploadGroup::class);
    }

//    /**
//     * @return ProducerUploadGroup[] Returns an array of ProducerUploadGroup objects
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

//    public function findOneBySomeField($value): ?ProducerUploadGroup
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
