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
    public function getProductRelatedCategories(array $relatedProducts, ?int $exclude_root_category = null, bool $onlyActive = true, ?array $categoryTypeIds = null, ?array $excludeCategoryTypeIds = null): array
    {
        $qb = $this->createQueryBuilder('category_product');
        $qb->select('IDENTITY(category_product.category) as id')
            ->andWhere(
                $qb->expr()->in(
                    'category_product.product',
                    $relatedProducts
                )
            );
        if ($exclude_root_category) {
            $qb->andWhere(
                $qb->expr()->notIn(
                    ':category',
                    'category_product.category'
                ))
                ->setParameter('category', $exclude_root_category)->getQuery()->getResult();
        }
        if ($onlyActive){
            $qb->leftJoin('category_product.product', 'product');
            $qb->leftJoin('category_product.category', 'category');
            $qb->andWhere('category.isActive = true')
                ->andWhere('product.isActive = true');
        }elseif ($categoryTypeIds || $excludeCategoryTypeIds){
            $qb->leftJoin('category_product.category', 'category');
        }

        if ($categoryTypeIds){
            $qb->andWhere('category.categoryType in (:category_types)')
                ->setParameter('category_types', $categoryTypeIds);
        }

        if ($excludeCategoryTypeIds){
            $qb->andWhere('category.categoryType in (:exclude_category_types)')
                ->setParameter('exclude_category_types', $excludeCategoryTypeIds);
        }
        $categories = $qb->getQuery()->getResult();
        return array_column($categories, 'id');
    }
}
