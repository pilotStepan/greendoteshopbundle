<?php

namespace Greendot\EshopBundle\Repository;

use Greendot\EshopBundle\Entity\ExternalIds;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExternalIds>
 *
 * @method ExternalIds|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExternalIds|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExternalIds[]    findAll()
 * @method ExternalIds[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExternalIdsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalIds::class);
    }

    public function save(ExternalIds $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExternalIds $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
