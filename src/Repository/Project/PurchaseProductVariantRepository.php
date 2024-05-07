<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PurchaseProductVariant|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseProductVariant|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseProductVariant[]    findAll()
 * @method PurchaseProductVariant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseProductVariant::class);
    }
}
