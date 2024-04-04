<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\PaymentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PaymentType|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaymentType|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaymentType[]    findAll()
 * @method PaymentType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentType::class);
    }
}
