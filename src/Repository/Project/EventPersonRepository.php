<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\EventPerson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventPerson>
 *
 * @method EventPerson|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventPerson|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventPerson[]    findAll()
 * @method EventPerson[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventPersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventPerson::class);
    }

    public function add(EventPerson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EventPerson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
