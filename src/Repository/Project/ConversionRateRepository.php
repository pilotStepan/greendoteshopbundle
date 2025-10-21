<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\ConversionRate;

/**
 * @extends ServiceEntityRepository<ConversionRate>
 *
 * @method ConversionRate|null find($id, $lockMode = null, $lockVersion = null)
 * @method ConversionRate|null findOneBy(array $criteria, array $orderBy = null)
 * @method ConversionRate[]    findAll()
 * @method ConversionRate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConversionRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversionRate::class);
    }

    public function save(ConversionRate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ConversionRate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
