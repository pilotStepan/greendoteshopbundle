<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\CategoryProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryProduct>
 *
 * @method CategoryProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryProduct[]    findAll()
 * @method CategoryProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryProduct::class);
    }

    public function add(CategoryProduct $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CategoryProduct $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }






    /**
     * @param array $relatedProducts //products from which to select categories
     * @param int|null $exclude_root_category //if certain category should be excluded (example root category from which products where selected)
     * @param bool $onlyActive //if select only in active products and categories
     * @param array|null $categoryTypeIds //select only particular categoryType categories - should not be used with excludeCategoryTypeIds
     * @param array|null $excludeCategoryTypeIds //select all categoryTypes but not these - should not be used with categoryTypeIds
     * @return array
     */
    /**
     * Ids of the categories the given products are in.
     *
     * @param list<int> $relatedProducts product ids
     * @param list<int>|null $categoryTypeIds only categories of these types
     * @param list<int>|null $excludeCategoryTypeIds no categories of these types
     * @return list<int>
     */
    public function getProductRelatedCategories(array $relatedProducts, ?int $exclude_root_category = null, bool $onlyActive = true, ?array $categoryTypeIds = null, ?array $excludeCategoryTypeIds = null): array
    {
        $qb = $this->createQueryBuilder('category_product')
            ->select('IDENTITY(category_product.category) as id')
            ->innerJoin('category_product.category', 'category')
            ->andWhere('category_product.product IN (:related_products)')
            ->setParameter('related_products', $relatedProducts);

        if ($exclude_root_category !== null) {
            $qb->andWhere('category.id != :exclude_root_category')
                ->setParameter('exclude_root_category', $exclude_root_category);
        }

        if ($onlyActive) {
            $qb->innerJoin('category_product.product', 'product')
                ->andWhere('category.isActive = true')
                ->andWhere('product.isActive = true');
        }

        if ($categoryTypeIds) {
            $qb->andWhere('category.categoryType IN (:category_types)')
                ->setParameter('category_types', $categoryTypeIds);
        }

        if ($excludeCategoryTypeIds) {
            $qb->andWhere('category.categoryType NOT IN (:exclude_category_types)')
                ->setParameter('exclude_category_types', $excludeCategoryTypeIds);
        }

        return array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'id'));
    }

     public function getMainCategoryForProduct($product){
        return $this->createQueryBuilder('cc')
            ->andWhere("cc.isMainCategory = 1")
            ->andWhere("cc.product = :id")
            ->setParameter("id", $product)
            ->getQuery()
            ->getOneOrNullResult()
            ;
    }
}
