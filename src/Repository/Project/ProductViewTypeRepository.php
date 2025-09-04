<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\ProductViewType;

/**
 * @extends ServiceEntityRepository<ProductViewType>
 */
class ProductViewTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductViewType::class);
    }
}
