<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubMenuType>
 *
 * @method SubMenuType|null find($id, $lockMode = null, $lockVersion = null)
 * @method SubMenuType|null findOneBy(array $criteria, array $orderBy = null)
 * @method SubMenuType[]    findAll()
 * @method SubMenuType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubMenuTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubMenuType::class);
    }
}
