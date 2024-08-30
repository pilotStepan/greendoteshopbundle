<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\ProductParameterGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductParameterGroup>
 *
 * @method ProductParameterGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductParameterGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductParameterGroup[]    findAll()
 * @method ProductParameterGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductParameterGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductParameterGroup::class);
    }
}
