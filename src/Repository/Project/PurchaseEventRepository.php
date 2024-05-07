<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\PurchaseEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseEvent>
 *
 * @method PurchaseEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseEvent[]    findAll()
 * @method PurchaseEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseEvent::class);
    }

    public function save(PurchaseEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PurchaseEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
