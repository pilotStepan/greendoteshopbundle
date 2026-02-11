<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\ProductProductType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductProductType>
 *
 * @method ProductProductType|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductProductType|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductProductType[]    findAll()
 * @method ProductProductType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductProductTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductProductType::class);
    }
}
