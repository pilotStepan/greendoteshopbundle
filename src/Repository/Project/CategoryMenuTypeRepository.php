<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\CategoryMenuType;

/**
 * @method CategoryMenuType|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryMenuType|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryMenuType[]    findAll()
 * @method CategoryMenuType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryMenuTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryMenuType::class);
    }
}