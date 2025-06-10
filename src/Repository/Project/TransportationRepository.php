<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Transportation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Transportation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transportation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transportation[]    findAll()
 * @method Transportation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transportation::class);
    }

    public function findOneByLowFree(string $country = null): Transportation|null
    {
        $qb =  $this->createQueryBuilder('t')
            ->join('t.handlingPrices', 'h')
            ->where('h.free_from_price > 0');
        if ($country !== null){
            $qb->andWhere('t.country = :country')->setParameter('country', $country);
        }


        $qb->orderBy('h.free_from_price', 'ASC')
            ->setMaxResults(1);
        return  $qb->getQuery()->getOneOrNullResult();
    }
}
