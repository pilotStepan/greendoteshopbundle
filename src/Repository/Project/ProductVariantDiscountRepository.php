<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\ProductVariantDiscount;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVariantDiscount>
 *
 * @method ProductVariantDiscount|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductVariantDiscount|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductVariantDiscount[]    findAll()
 * @method ProductVariantDiscount[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductVariantDiscountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariantDiscount::class);
    }

    public function save(ProductVariantDiscount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductVariantDiscount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findDiscount(ProductVariant|PurchaseProductVariant $variantOrPurchase, \DateTimeImmutable $dateTime = new \DateTimeImmutable()): ?ProductVariantDiscount
    {
        $date = $dateTime;
        $qb = $this->createQueryBuilder('d');

        if ($variantOrPurchase instanceof ProductVariant){
            $productVariant = $variantOrPurchase;
            $qb->andWhere('d.minimalAmount = 1');
            /*
            if ($dateTime == false){
                throw new \BadFunctionCallException(
                    "You can't set date to false while providing Project/ProductVariant as a first argument.\n 
                    Try providing Project/PurchaseProductVariant as first argument or set valid Date as second argument.",
                    500,
                    null
                );
            */
        }elseif($variantOrPurchase instanceof PurchaseProductVariant and $variantOrPurchase->getPurchase()->getId() != null){
            $date = $variantOrPurchase->getPurchase()->getDateIssue();
            $productVariant = $variantOrPurchase->getProductVariant();
            $qb->andWhere('d.minimalAmount <= :purchasedAmount')
                ->setParameter('purchasedAmount', $variantOrPurchase->getAmount());
        }else{
            $productVariant = $variantOrPurchase->getProductVariant();
        }

            $qb->andWhere($qb->expr()->orX(
                'd.dateEnd IS NULL',
                'd.dateEnd >= :dateend'
            ))
            ->andWhere('d.dateStart <= :datestart')
            ->setParameter('datestart', $date)
            ->setParameter('dateend', $date)
            ->andWhere('d.ProductVariant = :productVariant')
            ->setParameter('productVariant', $productVariant)
            ->orderBy("d.minimalAmount", "DESC")
            ->orderBy("d.discount", "DESC")
            ->setMaxResults(1)
            ;

        return $qb->getQuery()->getOneOrNullResult();
    }
//    /**
//     * @return ProductVariantDiscount[] Returns an array of ProductVariantDiscount objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ProductVariantDiscount
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
