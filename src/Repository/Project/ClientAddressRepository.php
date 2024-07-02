<?php

namespace App\Repository\Project;

use App\Entity\Project\ClientAddress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientAddress>
 *
 * @method ClientAddress|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientAddress|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientAddress[]    findAll()
 * @method ClientAddress[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientAddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientAddress::class);
    }

    //    /**
    //     * @return ClientAddress[] Returns an array of ClientAddress objects
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

    //    public function findOneBySomeField($value): ?ClientAddress
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
