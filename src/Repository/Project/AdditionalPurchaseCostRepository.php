<?php

namespace Greendot\EshopBundle\Repository\Project;


use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\AdditionalPurchaseCost;

/**
 * @extends ServiceEntityRepository<AdditionalPurchaseCost>
 *
 * @method AdditionalPurchaseCost|null find($id, $lockMode = null, $lockVersion = null)
 * @method AdditionalPurchaseCost|null findOneBy(array $criteria, array $orderBy = null)
 * @method AdditionalPurchaseCost[]    findAll()
 * @method AdditionalPurchaseCost[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AdditionalPurchaseCostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdditionalPurchaseCost::class);
    }

}
