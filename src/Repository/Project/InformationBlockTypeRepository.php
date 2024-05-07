<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\InformationBlockType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InformationBlockType>
 *
 * @method InformationBlockType|null find($id, $lockMode = null, $lockVersion = null)
 * @method InformationBlockType|null findOneBy(array $criteria, array $orderBy = null)
 * @method InformationBlockType[]    findAll()
 * @method InformationBlockType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InformationBlockTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InformationBlockType::class);
    }
}
