<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;

/**
 * @extends ServiceEntityRepository<ProductProduct>
 *
 * @method ProductProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductProduct[]    findAll()
 * @method ProductProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductProductRepository extends ServiceEntityRepository
{
    public function __construct(private readonly ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductProduct::class);
    }

    /**
     * Relations of a parent product whose child product can be loaded. The inner join lets Doctrine
     * filters (e.g. ProductActiveFilter) drop relations to hidden products; otherwise touching the
     * child proxy throws EntityNotFoundException.
     *
     * @return ProductProduct[]
     */
    public function findWithLoadableChildren(Product $parentProduct): array
    {
        return $this->createQueryBuilder('productProduct')
            ->addSelect('child', 'type')
            ->innerJoin('productProduct.childrenProduct', 'child')
            ->leftJoin('productProduct.productProductType', 'type')
            ->andWhere('productProduct.parentProduct = :parentProduct')
            ->setParameter('parentProduct', $parentProduct)
            ->getQuery()
            ->getResult();
    }

    public function getForPurchaseProductVariant(PurchaseProductVariant $purchaseProductVariant): ?ProductProduct
    {
        //get all products in purchase
        $productRepository = $this->registry->getRepository(Product::class);
        assert($productRepository instanceof ProductRepository);
        $productsInPurchase = $productRepository->findProductsByPurchaseQB($purchaseProductVariant->getPurchase());
        $productsInPurchase = $productsInPurchase
            ->select('product.id as id')
            ->distinct()
            ->getQuery()->getResult();
        //format to array of ids
        $productsInPurchase = array_column($productsInPurchase, 'id');

        //filter out current product
        $productsInPurchase = array_filter($productsInPurchase, fn($id) => $id !== $purchaseProductVariant->getProductVariant()->getProduct()->getId());

        if (!$productsInPurchase or empty($productsInPurchase)) return null;


        return $this->createQueryBuilder('productProduct')
            ->andWhere('productProduct.childrenProduct = :productVariant')
            ->setParameter('productVariant', $purchaseProductVariant->getProductVariant()->getProduct()->getId())
            ->andWhere('productProduct.parentProduct in (:productInPurchase)')
            ->setParameter('productInPurchase', $productsInPurchase)
            ->orderBy('productProduct.discount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
