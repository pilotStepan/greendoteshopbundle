<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\OpenTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OpenTime>
 *
 * @method OpenTime|null find($id, $lockMode = null, $lockVersion = null)
 * @method OpenTime|null findOneBy(array $criteria, array $orderBy = null)
 * @method OpenTime[]    findAll()
 * @method OpenTime[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OpenTimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OpenTime::class);
    }

    public function save(OpenTime $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OpenTime $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
