<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\ParameterGroupFormat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParameterGroupFormat>
 *
 * @method ParameterGroupFormat|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParameterGroupFormat|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParameterGroupFormat[]    findAll()
 * @method ParameterGroupFormat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParameterGroupFormatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParameterGroupFormat::class);
    }
}
