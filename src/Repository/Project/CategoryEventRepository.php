<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\CategoryEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryEvent>
 *
 * @method CategoryEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryEvent[]    findAll()
 * @method CategoryEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryEvent::class);
    }

    public function add(CategoryEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CategoryEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
