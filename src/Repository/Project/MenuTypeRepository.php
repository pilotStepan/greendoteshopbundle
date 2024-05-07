<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\MenuType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuType>
 *
 * @method MenuType|null find($id, $lockMode = null, $lockVersion = null)
 * @method MenuType|null findOneBy(array $criteria, array $orderBy = null)
 * @method MenuType[]    findAll()
 * @method MenuType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MenuTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuType::class);
    }
}
