<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\ParameterGroupFilterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParameterGroupFilterType>
 *
 * @method ParameterGroupFilterType|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParameterGroupFilterType|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParameterGroupFilterType[]    findAll()
 * @method ParameterGroupFilterType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParameterGroupFilterTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParameterGroupFilterType::class);
    }
}
