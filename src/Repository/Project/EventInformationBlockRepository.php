<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\EventInformationBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventInformationBlock>
 *
 * @method EventInformationBlock|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventInformationBlock|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventInformationBlock[]    findAll()
 * @method EventInformationBlock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventInformationBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventInformationBlock::class);
    }

//    /**
//     * @return EventInformationBlock[] Returns an array of EventInformationBlock objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?EventInformationBlock
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
