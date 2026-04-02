<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Voucher>
 */
class VoucherRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voucher::class);
    }

    public function findAllForClient(Client $client): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.Purchase_issued', 'p')
            ->where('p.client = :client')
            ->setParameter('client', $client)
            ->orderBy('v.date_issued', 'DESC')
            ->addOrderBy('v.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
