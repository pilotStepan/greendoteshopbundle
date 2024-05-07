<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\EventInformationBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventInformationBlock>
 *
 * @method EventInformationBlock|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventInformationBlock|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventInformationBlock[]    findAll()
 * @method EventInformationBlock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventInformationBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventInformationBlock::class);
    }
}
