<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

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

    public function findCheapestPriceForProduct(Product $product): ?Price
    {
        return $this->createQueryBuilder('p')
            ->select('p, (p.price * (1 - p.discount) * (1 + p.vat)) as HIDDEN calculatedPrice')
            ->join('p.productVariant', 'pv')
            ->where('pv.product = :product')
            ->andWhere('p.validFrom <= :now')
            ->andWhere('(p.validUntil IS NULL OR p.validUntil > :now)')
            ->setParameter('product', $product)
            ->setParameter('now', new \DateTime())
            ->orderBy('calculatedPrice', 'ASC') 
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


public function findCheapestPricesForProducts(array $productIds): array
{
    if (empty($productIds)) return [];

    // 1. Create a ResultSetMapping to turn raw SQL into Price entities
    $rsm = new ResultSetMappingBuilder($this->getEntityManager());
    $rsm->addRootEntityFromClassMetadata(Price::class, 'p');

    // 2. The SQL with a Window Function (ROW_NUMBER)
    $sql = "
        SELECT p_ordered.* FROM (
            SELECT p.*, 
                (p.price * (1 - COALESCE(p.discount, 0)) * (1 + COALESCE(p.vat, 0))) as calculated_price,
                ROW_NUMBER() OVER (
                    PARTITION BY pv.product_id 
                    ORDER BY (p.price * (1 - COALESCE(p.discount, 0)) * (1 + COALESCE(p.vat, 0))) ASC
                ) as price_rank
            FROM price p
            JOIN product_variant pv ON p.product_variant_id = pv.id
            WHERE pv.product_id IN (:productIds)
              AND pv.is_active = 1
              AND p.valid_from <= :now
              AND (p.valid_until IS NULL OR p.valid_until > :now)
        ) p_ordered
        WHERE p_ordered.price_rank = 1
    ";

    $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
    $query->setParameter('productIds', $productIds);
    $query->setParameter('now', new \DateTime());

    $results = $query->getResult();

    // 3. Map into a dictionary [productId => PriceEntity] for easy access
    $cheapestMap = [];
    foreach ($results as $price) {
        $cheapestMap[$price->getProductVariant()->getProduct()->getId()] = $price;
    }

    return $cheapestMap;
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

    public function findPricesByDateAndProductVariantNew(ProductVariant $productVariant, \DateTime $date, ?int $minimalAmount = 1, int|null $vat = null): array
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
            //->having($qb->expr()->max('p.discount'))
            ->orderBy('p.minimalAmount', 'DESC')
            ->addOrderBy('p.price', 'ASC')
            ->addOrderBy('p.discount', 'DESC')
        ;//->groupBy('p.minimalAmount');

        if ($minimalAmount and $minimalAmount > 0){
            $qb->andWhere('p.minimalAmount <= :minAmount')
                ->setParameter('minAmount', $minimalAmount);
        }
        if ($vat) {
            $qb->andWhere('p.vat = :vat')
                ->setParameter('vat', $vat);
        }

        $prices = $qb->getQuery()->getResult();
        $result = [];

        // Iterate through the original array and save only entities with unique minimalAmount
        foreach ($prices as $price) {
            assert($price instanceof Price);
            $isDiscounted = false;
            if ($price->getDiscount() > 0){
                $isDiscounted = true;
            }

            $uniqueMinimalAmount = $price->getMinimalAmount();

            $setPrice = $result[$uniqueMinimalAmount] ?? null;



            if (!$setPrice){ //if price for given minimal amount is not set
                if ($isDiscounted){
                    $result[$uniqueMinimalAmount]['discounted'] = $price;
                }else{
                    $result[$uniqueMinimalAmount]['price'] = $price;
                }
            } else{ //if price for given minimal amount is set
                if (!isset($setPrice['discounted']) and $isDiscounted){
                    $result[$uniqueMinimalAmount]['discounted'] = $price;
                }
                if (!isset($setPrice['price']) and !$isDiscounted){
                    $result[$uniqueMinimalAmount]['price'] = $price;
                }
            }

        }

        return $result;
    }

    public function getMinimalAmount(ProductVariant $productVariant, \DateTime $date = new \DateTime("now")): int
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
            ->orderBy('p.minimalAmount', 'ASC')
            ->setMaxResults(1);
        try {
            $qb = $qb->getQuery()->getSingleResult();
            if ($qb instanceof Price) {
                return $qb->getMinimalAmount();
            }
            return 0;
        } catch (NonUniqueResultException|NoResultException $exception) {
            return 0;
        }
    }

    public function getUniqueMinimalAmounts(ProductVariant $productVariant, \DateTime $date = new \DateTime("now")): array
    {
        $qb = $this->createQueryBuilder('p');
        $uniqueMinimalAmounts = $qb
            ->select('DISTINCT p.minimalAmount')
            ->andWhere('p.productVariant = :variant')
            ->andWhere('p.validFrom <= :date')
            ->andWhere($qb->expr()->orX('p.validUntil IS NULL', 'p.validUntil >= :date'))
            ->setParameter('variant', $productVariant)
            ->setParameter('date', $date)
            ->orderBy('p.minimalAmount', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
        return array_map('intval', $uniqueMinimalAmounts);
    }
}
