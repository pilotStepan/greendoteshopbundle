<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductUploadGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductUploadGroup>
 *
 * @method ProductUploadGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductUploadGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductUploadGroup[]    findAll()
 * @method ProductUploadGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductUploadGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductUploadGroup::class);
    }

    public function save(ProductUploadGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductUploadGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    /*
    public function getAllImagesForProduct(Product $product){
        return $this->createQueryBuilder('pug')
            ->leftJoin('pug.UploadGroup', 'ug')
            ->leftJoin('ug.productVariantUploadGroups', 'pvug')
            //->andWhere('pug.Product = :product')->setParameter('product', $product)
            ->andWhere('pvug.ProductVariant in (:variants)')->setParameter('variants', $product->getProductVariants())
            ->getQuery()->getResult();
    }*/

//    /**
//     * @return ProductUploadGroup[] Returns an array of ProductUploadGroup objects
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

//    public function findOneBySomeField($value): ?ProductUploadGroup
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
