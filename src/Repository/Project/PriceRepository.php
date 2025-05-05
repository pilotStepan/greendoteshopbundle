<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Product;
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

    public function findCheapestPriceForProduct(Product $product): ?Price
    {
        return $this->createQueryBuilder('p')
            ->join('p.productVariant', 'pv')
            ->where('pv.product = :product')
            ->andWhere('p.validFrom <= :now')
            ->andWhere('p.validUntil IS NULL OR p.validUntil > :now')
            ->setParameter('product', $product)
            ->setParameter('now', new \DateTime())
            ->orderBy('p.price', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
}
