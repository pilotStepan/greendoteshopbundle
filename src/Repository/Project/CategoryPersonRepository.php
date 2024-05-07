<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\CategoryPerson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryPerson>
 *
 * @method CategoryPerson|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryPerson|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryPerson[]    findAll()
 * @method CategoryPerson[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryPersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryPerson::class);
    }

    public function add(CategoryPerson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CategoryPerson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
