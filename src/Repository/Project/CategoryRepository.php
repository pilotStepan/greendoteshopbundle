<?php

namespace Greendot\EshopBundle\Repository\Project;

use Exception;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\Translatable\TranslatableListener;
use Greendot\EshopBundle\Entity\Project\Event;
use Greendot\EshopBundle\Entity\Project\Label;
use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\MenuType;
use Greendot\EshopBundle\Enum\CategoryTypeEnum;
use Greendot\EshopBundle\Repository\HintedRepositoryBase;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Greendot\EshopBundle\Entity\Project\CategoryType;
use Greendot\EshopBundle\Entity\Project\CategoryProduct;
use Greendot\EshopBundle\Entity\Project\CategoryCategory;
use Gedmo\Translatable\Query\TreeWalker\TranslationWalker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method Category|null find($id, $lockMode = null, $lockVersion = null)
 * @method Category|null findOneBy(array $criteria, array $orderBy = null)
 * @method Category[]    findAll()
 * @method Category[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryRepository extends HintedRepositoryBase
{
    private $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, private TranslatableListener $translatableListener, private RequestStack $requestStack)
    {
        parent::__construct($registry, Category::class);
        $this->entityManager = $entityManager;
    }

    public function findByNameLike(string $query, int $limit): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByNameLikeAndType(string $query, int $typeId, int $limit): array
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :query')
            ->andWhere('c.categoryType = :typeId')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('typeId', $typeId)
            ->setMaxResults($limit)
            ->getQuery();
        return $this->hintQuery($query)->getResult();
    }


    /**
     * @return Category[]
     */
    public function searchByName(string $name, ?CategoryTypeEnum $type = null, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->setMaxResults($limit);

        if ($type !== null) {
            $qb->andWhere('c.categoryType = :typeId')
                ->setParameter('typeId', $type->value);
        }

        $query = $qb->getQuery();
        $this->hintQuery($query);
        return $query->getResult();
    }

    /* @param string $name
     * @param CategoryTypeEnum[] $types
     * @param int $limit
     * @return Category[]
     */
    public function searchByNameAndTypes(string $name, array $types, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->setMaxResults($limit);

        if (!empty($types)) {
            $qb->andWhere('c.categoryType IN (:types)')
                ->setParameter('types', array_map(fn($type) => $type?->value, $types));
        }

        return $this->hintQuery($qb->getQuery())->getResult();
    }

    public function findCategorySiblings(int $categoryId): array
    {
        $category = $this->find($categoryId);
        $categoryCategoryRepository = $this->entityManager->getRepository(CategoryCategory::class);

        $categoryCategory = $categoryCategoryRepository->findOneBy(['category_sub' => $category]);
        $parentCategory = $categoryCategory->getCategorySuper();

        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.categorySubCategories', 'cc')
            ->where('cc.category_super = :parentCategory')
            ->andWhere('c.id != :categoryId')
            ->setParameter('parentCategory', $parentCategory->getId())
            ->setParameter('categoryId', $categoryId);

        $siblings = array_map(function (Category $category) {
            return [
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ];
        }, $this->hintQuery($qb->getQuery())->getResult());

        $parent = [
            'name' => $parentCategory->getName(),
            'slug' => $parentCategory->getSlug()
        ];

        return [
            'parent' => $parent,
            'siblings' => $siblings
        ];
    }

    public function findMenuCategories(MenuType|int $menuType)
    {
        $menuTypeId = $menuType instanceof MenuType ? $menuType->getId() : $menuType;
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :is_active')
            ->setParameter('is_active', 1)
            ->andWhere('c.id >= :val')
            ->setParameter('val', 2)
            ->leftJoin('c.categorySubCategories', 'a')
            ->andWhere('a is NULL')
            ->leftJoin('c.menuType', 'mt')
            ->andWhere('mt.menu_type = :menuTypeId')
            ->setParameter('menuTypeId', $menuTypeId)
            ->orderBy('mt.sequence', 'ASC')
            ->getQuery();

        return $this->hintQuery($query)->getResult();
    }

    /**
     * @param Category $category
     * @param SubMenuType $menuType
     * @param int[] $allowedCategoryTypes
     * @param int[] $excludedCategoryTypes
     * @return mixed
     */
    public function findSubMenuCategories(Category $category, SubMenuType $menuType, array $allowedCategoryTypes = [], array $excludedCategoryTypes = [])
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.subMenuType', 'mt')
            ->leftJoin('c.categorySubCategories', 'a')
            ->andWhere('c.isActive = :is_active')
            ->setParameter('is_active', 1)
            ->andWhere('c.id >= :val')
            ->setParameter('val', 2)
            ->andWhere('c.id != :id')
            ->setParameter('id', $category->getId())
            ->andWhere('a.category_super = :cat')
            ->setParameter('cat', $category->getId())
            ->andWhere('mt.id = :menuType')
            ->setParameter('menuType', $menuType->getId())
            ->orderBy('c.sequence', 'ASC');

        if ($allowedCategoryTypes) {
            $qb->andWhere('c.categoryType IN (:allowedCategoryTypes)')
                ->setParameter('allowedCategoryTypes', $allowedCategoryTypes);
        }
        if ($excludedCategoryTypes) {
            $qb->andWhere('c.categoryType NOT IN (:excludedCategoryTypes)')
                ->setParameter('excludedCategoryTypes', $excludedCategoryTypes);
        }

        //dd($qb->getSQL());
        return $this->hintQuery($qb->getQuery())->getResult();
    }

    public function findMenuCategoriesByMenuID($menu_id)
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.id >= :val')
            ->setParameter('val', 2)
            ->andWhere('c.isActive = :is_active')
            ->setParameter('is_active', 1)
            ->andWhere('c.is_menu = :menu_id')
            ->setParameter('menu_id', $menu_id)
            ->leftJoin('c.categorySubCategories', 'a')
            ->andWhere('a is NULL')
            ->orderBy('c.sequence', 'ASC')
            ->getQuery();
        return $this->hintQuery($query)->getResult();
    }

    public function findMainMenuCategories(int $withhidden = 0)
    {
        $result = $this->createQueryBuilder('c')
            ->where('c.id > 1')
            ->leftJoin('c.categorySubCategories', 'a')
            ->andWhere('a is NULL')
            ->orderBy('c.sequence', 'ASC');

        if ($withhidden == false) {
            $result->andWhere('c.id > 10');
        }

        return $this->hintQuery($result
            ->getQuery())->getResult();
    }

    public function findActiveSubCategories(int $id, $filterSystem = true)
    {
        $result = $this->createQueryBuilder('c')
            ->leftJoin('c.categorySubCategories', 'a')
            ->where('a.category_super = :cat')
            ->setParameter('cat', $id)
            ->andWhere('c.isActive = :active')
            ->setParameter('active', 1)
            ->orderBy('a.sequence', 'ASC');
        if ($filterSystem) {
            $result->andWhere('c.id > 10');
        }
        return $this->hintQuery($result->getQuery())->getResult();
    }

    public function findBlogCategoriesByLabel(Label|int $label, int $limit = null)
    {
        if ($label instanceof Label) {
            $label = $label->getId();
        }
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.isActive = 1')
            ->leftJoin('c.labels', 'l')
            ->andWhere('l = :labelID')
            ->andWhere('c.categoryType = 6')
            ->setParameter('labelID', $label);
        if ($limit) {
            $result->setMaxResults($limit);
        }
        return $this->hintQuery($result->getQuery())->getResult();
    }

    public function getCategoryRelatedCategoriesBothWays(int|Category $category, bool $onlyActive = true, ?int $categoryTypeID = null): array
    {
        if ($category instanceof Category) {
            $category = $category->getId();
        }

        /*
         * query split to two - may come useful someday
         *
        $relatedAsSubResult = [];
        $relatedAsSuperResult = [];
        $relatedAsSub = $this->createQueryBuilder('relatedAsSub')
            ->leftJoin('relatedAsSub.categorySubCategories', 'category_sub')
            ->andWhere('category_sub.category_super = :category_id')
                ->setParameter('category_id', $category)
            ->andWhere('relatedAsSub.categoryType = :categoryTypeId')
                ->setParameter('categoryTypeId', $categoryTypeID);
        if ($onlyActive){
            $relatedAsSub->andWhere('relatedAsSub.isActive = 1');
        }
        $relatedAsSubResult = $relatedAsSub->getQuery()->getResult();

        $relatedAsSuper = $this->createQueryBuilder('relatedAsSuper')
            ->leftJoin('relatedAsSuper.categoryCategories', 'category_super')
            ->andWhere('category_super.category_sub = :category_id')
                ->setParameter('category_id', $category)
            ->andWhere('relatedAsSuper.categoryType = :categoryTypeId')
                ->setParameter('categoryTypeId', $categoryTypeID)
            ;
        if ($onlyActive){
            $relatedAsSuper
            ->andWhere('relatedAsSuper.isActive = 1');
        }
        $relatedAsSuperResult = $relatedAsSuper->getQuery()->getResult();
        return array_merge($relatedAsSuperResult, $relatedAsSubResult);
        */

        $relatedQueryBuilder = $this->createQueryBuilder('related')
            ->leftJoin('related.categorySubCategories', 'category_sub')
            ->leftJoin('related.categoryCategories', 'category_super')
            ->where('category_sub.category_super = :category_id OR category_super.category_sub = :category_id')
            ->andWhere('related.categoryType = :categoryTypeId')
            ->setParameter('category_id', $category)
            ->setParameter('categoryTypeId', $categoryTypeID);

        if ($onlyActive) {
            $relatedQueryBuilder->andWhere('related.isActive = 1');
        }

        return $this->hintQuery($relatedQueryBuilder->getQuery())->getResult();

    }


    /**
     * @throws Exception
     */
    public function getCategoriesForEntity($entity, $onlyActive = true, $categoryTypeID = null)
    {
        $qb = $this->createQueryBuilder('c');
        if ($onlyActive) {
            $qb->andWhere('c.isActive = :active')->setParameter('active', true);
        }
        if ($categoryTypeID) {
            $qb->leftJoin('c.categoryType', 'ct')
                ->andWhere('ct.id = :ct_id')
                ->setParameter('ct_id', $categoryTypeID);
        }
        if ($entity instanceof Category) {
            $qb
                ->leftJoin('c.categorySubCategories', 'cc')
                ->andWhere('cc.category_super = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('cc.sequence', 'ASC');

        } elseif ($entity instanceof Product) {
            $qb
                ->leftJoin('c.categoryProducts', 'cp')
                ->andWhere('cp.product = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('cp.sequence', 'ASC');

        } elseif ($entity instanceof Person) {
            $qb
                ->leftJoin('c.persons', 'cp')
                ->andWhere('cp.person = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('cp.sequence', 'ASC');

        } elseif ($entity instanceof Event) {
            $qb
                ->leftJoin('c.events', 'ce')
                ->andWhere('ce.event = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('ce.sequence', 'ASC');
        } else {
            throw new Exception('Unknown entity type');
        }

        return $this->hintQuery($qb->getQuery())->getResult();
    }

    public function getSubcategories(int|Category $category): array
    {
        if ($category instanceof Category) $category = $category->getId();

        $query = $this->createQueryBuilder('category')
            ->leftJoin('category.categorySubCategories', 'sub_categories')
            ->andWhere('sub_categories.category_super = :cat')
            ->setParameter('cat', $category)
            ->getQuery();
        return $this->hintQuery($query)->getResult();

    }

    /**
     * returns categories of products related to given category/categories - if category entity is given excludes it from select
     *
     * @param array|Category $category
     * @param bool $onlyActive //if related products and final categoris should be active
     * @param array|null $categoryTypeIds //select only particular categoryType categories - should not be used with excludeCategoryTypeIds
     * @param array|null $excludeCategoryTypeIds //select all categoryTypes but not these - should not be used with categoryTypeIds
     * @return array
     */
    public function getCategoriesFromRelatedProducts(array|Category $category, bool $onlyActive = true, ?array $categoryTypeIds = null, ?array $excludeCategoryTypeIds = null): array
    {
        //three queries for few categories is a lot, but I was not able to figure out a better way
        $excludeCategory = null;
        //returns related products for category
        $relatedProducts = $this->createQueryBuilder('c')
            ->select('IDENTITY(category_products.product) as id')
            ->leftJoin('c.categoryProducts', 'category_products');
        if ($category instanceof Category) {
            $excludeCategory = $category->getId();
            $relatedProducts = $relatedProducts->andWhere('c.id = :category')
                ->setParameter('category', $excludeCategory);
        } else {
            $relatedProducts = $relatedProducts->andWhere('c.id in (:categories)')
                ->setParameter('categories', $category);
        }

        $relatedProducts = $relatedProducts->getQuery()->getResult();
        $relatedProducts = array_column($relatedProducts, 'id');
        if (count($relatedProducts) < 1 || $relatedProducts[0] === null) {
            return [];
        }
        $categories = $this->entityManager->getRepository(CategoryProduct::class)->getProductRelatedCategories($relatedProducts, $excludeCategory, $onlyActive, $categoryTypeIds, $excludeCategoryTypeIds);

        //hydrates category
        $query = $this->createQueryBuilder('c')->andWhere('c.id in (:categorie)')->setParameter('categorie', $categories)->getQuery();
        return $this->hintQuery($query)->getResult();
    }
}
