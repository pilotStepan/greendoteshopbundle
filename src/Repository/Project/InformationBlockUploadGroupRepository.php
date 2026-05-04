<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\InformationBlockUploadGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InformationBlockUploadGroup>
 *
 * @method InformationBlockUploadGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method InformationBlockUploadGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method InformationBlockUploadGroup[]    findAll()
 * @method InformationBlockUploadGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InformationBlockUploadGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InformationBlockUploadGroup::class);
    }

}
