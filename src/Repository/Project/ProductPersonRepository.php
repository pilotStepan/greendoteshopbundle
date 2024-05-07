<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\ProductPerson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductPerson>
 *
 * @method ProductPerson|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductPerson|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductPerson[]    findAll()
 * @method ProductPerson[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductPersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductPerson::class);
    }
}
