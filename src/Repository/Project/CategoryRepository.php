<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\CategoryCategory;
use Greendot\EshopBundle\Entity\Project\MenuType;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\Translatable\Query\TreeWalker\TranslationWalker;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\HttpFoundation\RequestStack;

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

    public function findSubMenuCategories(Category $category, MenuType $menuType)
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.menuType', 'mt')
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
            ->getQuery()
            ->getResult();
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

    public function findSubCategories(int $id, $filterSystem = true)
    {
        $result = $this->createQueryBuilder('c')
            ->leftJoin('c.categorySubCategories', 'a')
            ->where('a.category_super = :cat')
            ->setParameter('cat', $id)
            ->orderBy('c.sequence', 'ASC');
        if ($filterSystem) {
            $result->andWhere('c.id > 10');
        }
        return $result->getQuery()->getResult();
    }

    public function findMainMenuCategories(int $withhidden = 0)
    {
        /*
        $conn = $this->entityManager->getConnection();
        $sql = "SELECT p_category.id, p_category.name FROM p_category LEFT JOIN p_category_category ON p_category.id = p_category_category.category_sub_id WHERE p_category_category.id IS NULL AND p_category.id > 10 GROUP BY p_category.name ORDER BY p_category.sequence ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        */

        /*$sub = $this->getEntityManager('project')->createQueryBuilder()
            ->select('IDENTITY(r.category_sub)')
            ->from('Greendot\EshopBundle\Entity\Project\CategoryCategory', 'r');
*/
        //dd($sub->getQuery()->getSQL());


        $result = $this->createQueryBuilder('c')
            ->where('c.id > 1')
            ->leftJoin('c.categorySubCategories', 'a')
            ->andWhere('a is NULL')
            ->orderBy('c.sequence', 'ASC');

        if ($withhidden == false) {
            $result->andWhere('c.id > 10');
        }

        /*
                return $this->getEntityManager('project')->createQueryBuilder()
                    ->select('c')
                    ->from(Category::class, 'c')
                    ->andWhere($sub->expr()->notIn('c.id', $sub->getDQL()))
                    ->andWhere('c.id > 10')
                    ->getQuery()->getSQL();*/

        return $result
            ->getQuery()->getResult();
    }

    public function findExistingSlug($slug)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findExistingSlugById($slug, $id)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->andWhere('c.id != :id')
            ->setParameter('id', $id)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMaxSequence()
    {
        return $this->createQueryBuilder('c')
            ->select('MAX(c.sequence) AS max_sequence')
            ->getQuery()
            ->getResult();
    }

    public function getMostInferiorCategories()
    {
        $qb = $this->createQueryBuilder("c")
            ->leftJoin("c.categoryCategories", "cc")
            ->andWhere("c.is_menu = 2")
            ->getQuery()->getResult();

        return $qb;

    }

    /**
     * @return Category[]
     **/
    public function getLastLayerCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.categoryCategories is empty')
            ->andWhere('c.is_menu = 2')
            ->getQuery()->getResult();
    }

    public function findCategoryByExternalID($externalID): Category
    {
        return $this->createQueryBuilder("c")
            ->leftJoin("c.parameters", 'p')
            ->andWhere('p.data = :externalID')
            ->setParameter('externalID', $externalID)
            ->getQuery()
            ->getSingleResult();

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

    public function getProductCategoriesForIndexing(Product $product)
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.categoryProducts', 'cp')
            ->select('c.id', 'c.name', 'c.menu_name')
            ->andWhere('cp.product = :product')->setParameter('product', $product)
            ->andWhere('c.isActive = 1')
            ->distinct()
            ->getQuery()->getArrayResult();
    }


    /**
     * @return Category[]
     **/
    public function getUpperLayer(array $lowerLevel): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.categoryCategories', 'cc')
            ->andWhere('cc.category_sub in (:lowerLevel)')->setParameter('lowerLevel', $lowerLevel)
            ->andWhere('c.is_menu = 2')
            ->getQuery()->getResult();
    }

    public function firstWithImageFromArray(array $categories): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id in (:categories)')->setParameter('categories', $categories)
            ->andWhere('c.upload is not null')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }


    public function hasProduct(Category $category, Product $product): bool
    {
        $result = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->leftJoin('c.categoryProducts', 'cp')
            ->andWhere('cp.product = :product')
            ->andWhere('cp.category = :category')
            ->setParameter('product', $product)
            ->setParameter('category', $category)
            ->getQuery()->getSingleScalarResult();

        return $result > 0;;
    }
    // /**
    //  * @return Category[] Returns an array of Category objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
