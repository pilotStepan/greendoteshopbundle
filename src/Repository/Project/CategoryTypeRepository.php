<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\CategoryType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryType>
 *
 * @method CategoryType|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryType|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryType[]    findAll()
 * @method CategoryType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryType::class);
    }

    public function save(CategoryType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CategoryType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
