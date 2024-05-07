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
}
