<?php

namespace App\Repository;

use App\Entity\ExternalIds;
use App\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

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

    public function hasBeenAdded(string $externalId, ProductVariant $productVariant):int
    {
        return $this->createQueryBuilder('ei')
            ->select("count(ei.id)")
            ->leftJoin('ei.parameterGroup', 'param')
            ->andWhere('ei.externalId = :externalId')->setParameter('externalId', $externalId)
            ->andWhere('param.productVariant = :productVariant')->setParameter('productVariant', $productVariant)
            ->getQuery()->getSingleScalarResult();


    }

//    /**
//     * @return ExternalIds[] Returns an array of ExternalIds objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ExternalIds
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
