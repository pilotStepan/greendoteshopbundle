<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\CategoryFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CategoryFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryFile|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryFile[]    findAll()
 * @method CategoryFile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryFile::class);
    }
}
