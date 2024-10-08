<?php

namespace Greendot\EshopBundle\Repository;

use Greendot\EshopBundle\Entity\PurchaseTracking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseTracking>
 *
 * @method PurchaseTracking|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseTracking|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseTracking[]    findAll()
 * @method PurchaseTracking[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseTrackingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseTracking::class);
    }

    public function save(PurchaseTracking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PurchaseTracking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
