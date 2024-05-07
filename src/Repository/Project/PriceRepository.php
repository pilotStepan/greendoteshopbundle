<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Price>
 *
 * @method Price|null find($id, $lockMode = null, $lockVersion = null)
 * @method Price|null findOneBy(array $criteria, array $orderBy = null)
 * @method Price[]    findAll()
 * @method Price[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Price::class);
    }

    public function save(Price $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Price $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findPricesByDateAndProductVariant(ProductVariant $productVariant, \DateTime $date, ?int $minimalAmount = 1, int|null $vat = null): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb
            ->andWhere('p.productVariant = :productVariant')
            ->setParameter('productVariant', $productVariant)
            ->andWhere('p.validFrom <= :date')
            ->setParameter('date', $date)
            ->andWhere(
                $qb->expr()->orX(
                    'p.validUntil >= :date',
                    'p.validUntil IS NULL'
                )
            )
            ->orderBy('p.minimalAmount', 'DESC')
            ->addOrderBy('p.price', 'ASC')
            ->groupBy('p.minimalAmount');
        if ($minimalAmount and $minimalAmount > 0){
            $qb->andWhere('p.minimalAmount <= :minAmount')
            ->setParameter('minAmount', $minimalAmount);
        }
        if ($vat) {
            $qb->andWhere('p.vat = :vat')
                ->setParameter('vat', $vat);
        }
        return $qb->getQuery()->getResult();
    }
}
