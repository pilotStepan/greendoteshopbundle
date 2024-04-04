<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\UploadGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UploadGroup>
 *
 * @method UploadGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method UploadGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method UploadGroup[]    findAll()
 * @method UploadGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UploadGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadGroup::class);
    }

    public function save(UploadGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UploadGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getAllUploadGroupsForProduct(Product $product, $accountVariants = true): array
    {
        /*
        return $this->createQueryBuilder('pug')
            ->leftJoin('pug.UploadGroup', 'ug')
            ->leftJoin('ug.productVariantUploadGroups', 'pvug')
            //->andWhere('pug.Product = :product')->setParameter('product', $product)
            ->andWhere('pvug.ProductVariant in (:variants)')->setParameter('variants', $product->getProductVariants())
            ->getQuery()->getResult();*/

        $productUploadGroups = $this->createQueryBuilder('ug')
            ->leftJoin('ug.productUploadGroup', 'pug')
            ->andWhere('pug.Product = :product')->setParameter('product', $product)
            ->getQuery()->getResult();
        if ($accountVariants){
            $variantUploadGroups = $this->createQueryBuilder('ug')
                ->leftJoin('ug.productVariantUploadGroups', 'pvug')
                ->andWhere('pvug.ProductVariant in (:variants)')->setParameter('variants', $product->getProductVariants())
                ->getQuery()->getResult();
            return array_merge($productUploadGroups, $variantUploadGroups);
        }

        return  $productUploadGroups;

    }

//    /**
//     * @return UploadGroup[] Returns an array of UploadGroup objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?UploadGroup
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
