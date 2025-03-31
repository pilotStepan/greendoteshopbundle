<?php

namespace Greendot\EshopBundle\Repository\Project;

use Exception;
use Doctrine\ORM\Query;
use Greendot\EshopBundle\Entity\Project\Event;
use Greendot\EshopBundle\Entity\Project\Label;
use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\MenuType;
use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Greendot\EshopBundle\Entity\Project\CategoryCategory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Gedmo\Translatable\Query\TreeWalker\TranslationWalker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method Category|null find($id, $lockMode = null, $lockVersion = null)
 * @method Category|null findOneBy(array $criteria, array $orderBy = null)
 * @method Category[]    findAll()
 * @method Category[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryRepository extends ServiceEntityRepository
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
        return $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :query')
            ->andWhere('c.categoryType = :typeId')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('typeId', $typeId)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function searchByName($name)
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :name')->setParameter('name', '%' . $name . '%')
            ->getQuery();
        $query->setHint(
            Query::HINT_CUSTOM_OUTPUT_WALKER,
            TranslationWalker::class
        );
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $this->requestStack->getCurrentRequest()->getLocale() // take locale from session or request etc.
        );
        return $query->getResult();
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
        }, $qb->getQuery()->getResult());

        $parent = [
            'name' => $parentCategory->getName(),
            'slug' => $parentCategory->getSlug()
        ];

        return [
            'parent' => $parent,
            'siblings' => $siblings
        ];
    }

    public function findMenuCategories(MenuType $menuType)
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.menuType', 'mt')
            ->andWhere('c.isActive = :is_active')
            ->setParameter('is_active', 1)
            ->andWhere('c.id >= :val')
            ->setParameter('val', 2)
            ->andWhere('mt.id = :menuType')
            ->setParameter('menuType', $menuType->getId())
            ->leftJoin('c.categorySubCategories', 'a')
            ->andWhere('a is NULL')
            ->orderBy('c.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findSubMenuCategories(Category $category, SubMenuType $menuType)
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

            ->orderBy('c.sequence', 'ASC')
            ->getQuery();

        //dd($qb->getSQL());
        return $qb->getResult();
    }

    public function findMenuCategoriesByMenuID($menu_id)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id >= :val')
            ->setParameter('val', 2)
            ->andWhere('c.isActive = :is_active')
            ->setParameter('is_active', 1)
            ->andWhere('c.is_menu = :menu_id')
            ->setParameter('menu_id', $menu_id)
            ->leftJoin('c.categorySubCategories', 'a')
            ->andWhere('a is NULL')
            ->orderBy('c.sequence', 'ASC')
            ->getQuery()
            ->getResult();
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

        return $result
            ->getQuery()->getResult();
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
        return $result->getQuery()->getResult();
    }

    public function findBlogCategoriesByLabel(Label|int $label, int $limit = null)
    {
        if ($label instanceof Label){
            $label = $label->getId();
        }
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.isActive = 1')
            ->leftJoin('c.labels', 'l')
            ->andWhere('l = :labelID')
            ->andWhere('c.categoryType = 6')
            ->setParameter('labelID', $label);
        if($limit) {
            $result->setMaxResults($limit);
        }
        return $result->getQuery()->getResult();
    }

    public function getCategoryRelatedCategoriesBothWays(int|Category $category,bool $onlyActive = true,?int $categoryTypeID = null) : array
    {
        if ($category instanceof Category){
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

        $relatedResult = $relatedQueryBuilder->getQuery()->getResult();

        return $relatedResult;

    }


    /**
     * @throws Exception
     */
    public function getCategoriesForEntity($entity, $onlyActive = true, $categoryTypeID = null)
    {
        $qb = $this->createQueryBuilder('c');
        if ($onlyActive){
            $qb->andWhere('c.isActive = :active')->setParameter('active', true);
        }
        if ($categoryTypeID){
            $qb->leftJoin('c.categoryType', 'ct')
                ->andWhere('ct.id = :ct_id')
                ->setParameter('ct_id', $categoryTypeID);
        }
        if ($entity instanceof Category){
            $qb
                ->leftJoin('c.categorySubCategories', 'cc')
                ->andWhere('cc.category_super = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('cc.sequence', 'ASC');

        } elseif ($entity instanceof Product){
            $qb
                ->leftJoin('c.categoryProducts', 'cp')
                ->andWhere('cp.product = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('cp.sequence', 'ASC');

        } elseif ($entity instanceof Person){
            $qb
                ->leftJoin('c.persons', 'cp')
                ->andWhere('cp.person = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('cp.sequence', 'ASC');

        } elseif ($entity instanceof Event){
            $qb
                ->leftJoin('c.events', 'ce')
                ->andWhere('ce.event = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('ce.sequence', 'ASC');
        } else {
            throw new Exception('Unknown entity type');
        }

        return $qb->getQuery()->getResult();
    }

    public function getSubcategories(int|Category $category): array
    {
        if ($category instanceof Category) $category = $category->getId();

        return $this->createQueryBuilder('category')
            ->leftJoin('category.categorySubCategories', 'sub_categories')
            ->andWhere('sub_categories.category_super = :cat')
            ->setParameter('cat', $category)
            ->getQuery()->getResult();

    }
}
