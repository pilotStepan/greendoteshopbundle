<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\CategoryCategory;
use Greendot\EshopBundle\Entity\Project\Event;
use Greendot\EshopBundle\Entity\Project\MenuType;
use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
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
}
