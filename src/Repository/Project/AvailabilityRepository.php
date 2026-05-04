<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Availability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;

/**
 * @extends ServiceEntityRepository<Availability>
 *
 * @method Availability|null find($id, $lockMode = null, $lockVersion = null)
 * @method Availability|null findOneBy(array $criteria, array $orderBy = null)
 * @method Availability[]    findAll()
 * @method Availability[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Availability::class);
    }

    public function add(Availability $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Availability $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getAvailabilityForProduct(Product|int $product): ?Availability
    {
        if ($product instanceof Product) $product = $product->getId();

        return $this->createQueryBuilder('availability')
            ->leftJoin('availability.productVariants', 'productVariant')
            ->where('productVariant.product = :product')
            ->setParameter('product', $product)
            ->orderBy('availability.sequence', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


    public function getAvailabilityForProductIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $availabilityIds = $this->createQueryBuilder('a')
            ->select('a.id AS availId', 'IDENTITY(pv.product) AS productId')
            ->innerJoin('a.productVariants', 'pv')
            ->where('pv.product IN (:ids)')
            ->setParameter('ids', $productIds)
            ->orderBy('a.sequence', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $idMap = [];
        $uniqueAvailabilityIds = [];

        foreach ($availabilityIds as $row) {
            $pId = $row['productId'];
            $aId = $row['availId'];

            if (!isset($idMap[$pId])) {
                $idMap[$pId] = $aId;
                $uniqueAvailabilityIds[$aId] = true;
            }
        }

        $availabilities = $this->findBy(['id' => array_keys($uniqueAvailabilityIds)]);
        
        $entityMap = [];
        foreach ($availabilities as $avail) {
            $entityMap[$avail->getId()] = $avail;
        }

        $finalMap = [];
        foreach ($idMap as $pId => $aId) {
            if (isset($entityMap[$aId])) {
                $finalMap[$pId] = $entityMap[$aId];
            }
        }

        return $finalMap;
    }
    
}
