<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\PersonInformationBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonInformationBlock>
 *
 * @method PersonInformationBlock|null find($id, $lockMode = null, $lockVersion = null)
 * @method PersonInformationBlock|null findOneBy(array $criteria, array $orderBy = null)
 * @method PersonInformationBlock[]    findAll()
 * @method PersonInformationBlock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonInformationBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonInformationBlock::class);
    }

//    /**
//     * @return PersonInformationBlock[] Returns an array of PersonInformationBlock objects
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

//    public function findOneBySomeField($value): ?PersonInformationBlock
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
