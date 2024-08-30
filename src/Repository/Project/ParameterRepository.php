<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\CategoryParameterGroup;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use App\Service\CategoryInfoGetter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Parameter>
 *
 * @method Parameter|null find($id, $lockMode = null, $lockVersion = null)
 * @method Parameter|null findOneBy(array $criteria, array $orderBy = null)
 * @method Parameter[]    findAll()
 * @method Parameter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParameterRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private CategoryInfoGetter $categoryInfoGetter,
        private CategoryRepository $categoryRepository,
        private ParameterGroupRepository $parameterGroupRepository
    )
    {
        parent::__construct($registry, Parameter::class);
    }

    public function findAvailableParametersByColorOrSize($productId, $color = null, $size = null)
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.productVariant', 'pv')
            ->where('pv.product = :productId')
            ->setParameter('productId', $productId);

        if ($color) {
            $subQb = $this->createQueryBuilder('subP')
                ->select('DISTINCT pv.id')
                ->join('subP.productVariant', 'pv')
                ->where('subP.data = :color')
                ->andWhere('pv.product = :productId')
                ->setParameter('color', $color)
                ->setParameter('productId', $productId);

            $variantIds = $subQb->getQuery()->getScalarResult();
            $variantIds = array_column($variantIds, 'id');

            $qb->andWhere($qb->expr()->in('pv.id', ':variantIds'))
                ->andWhere('p.parameterGroup = :parameterGroupColor')
                ->setParameter('variantIds', $variantIds)
                ->setParameter('parameterGroupColor', 2);
        }

        if ($size) {
            $subQb = $this->createQueryBuilder('subP')
                ->select('DISTINCT pv.id')
                ->join('subP.productVariant', 'pv')
                ->where('subP.data = :size')
                ->andWhere('pv.product = :productId')
                ->setParameter('size', $size)
                ->setParameter('productId', $productId);

            $variantIds = $subQb->getQuery()->getScalarResult();
            $variantIds = array_column($variantIds, 'id');

            $qb->andWhere($qb->expr()->in('pv.id', ':variantIds'))
                ->andWhere('p.parameterGroup = :parameterGroupSize')
                ->setParameter('variantIds', $variantIds)
                ->setParameter('parameterGroupSize', 1);
        }

        $qb->groupBy('p.data');

        return $qb->getQuery()->getResult();
    }

    public function add(Parameter $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Parameter $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findParametersQB(QueryBuilder $queryBuilder, int $parameterGroupId): QueryBuilder
    {
        $alias = $queryBuilder->getRootAliases()[0];

        return $queryBuilder
            ->andWhere($alias . '.parameterGroup = :parameterGroupId')
            ->setParameter('parameterGroupId', $parameterGroupId);
    }

    public function getFilterTree(int $categoryId): array
    {
        $categoryParameterGroups = $this->getEntityManager()->getRepository(CategoryParameterGroup::class)
            ->createQueryBuilder('cpg')
            ->select('cpg', 'pg')
            ->innerJoin('cpg.parameterGroup', 'pg')
            ->where('cpg.category = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($categoryParameterGroups as $categoryParameterGroup) {
            $parameterGroup = $categoryParameterGroup->getParameterGroup();

            $parameters = $this->getEntityManager()->getRepository(Parameter::class)
                ->createQueryBuilder('p')
                ->select('p')
                ->where('p.parameterGroup = :parameterGroup')
                ->setParameter('parameterGroup', $parameterGroup)
                ->getQuery()
                ->getResult();

            $groupedParameters = [];
            foreach ($parameters as $parameter) {
                $data = $parameter->getData();
                if (!in_array($data, $groupedParameters)) {
                    $groupedParameters[] = $data;
                }
            }

            $result[$parameterGroup->getName()] = $groupedParameters;
        }

        return $result;
    }

    public function getByGroupAndMostSuperiorCategoryQB(ParameterGroup $parameterGroup, Category $category){

        $allCategories = $this->categoryInfoGetter->getAllSubCategories($category);
        $categoryids = [];
        foreach ($allCategories as $category){
            $categoryids[] = $category->getId();
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.parameterGroup = :parameterGroup')
            ->setParameter('parameterGroup', $parameterGroup)
            ->join('p.productVariant', 'pv')
            ->join('pv.product', 'pr')
            ->join('pr.categoryProducts', 'cp')
            ->andWhere('cp.category IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryids)
            ->groupBy('p.data')
            ->getQuery()->getResult();
    }

    public function getByManufacturerGroupAndMostSuperiorCategoryQB(QueryBuilder $queryBuilder, int $category){

        $category = $this->categoryRepository->find($category);
        $parameterGroup = $this->parameterGroupRepository->findOneBy(["name" => "Manufacturer"]);
        $alias = $queryBuilder->getRootAliases()[0];

        $allCategories = $this->categoryInfoGetter->getAllSubCategories($category);
        $categoryids = [];
        foreach ($allCategories as $category){
            $categoryids[] = $category->getId();
        }

        return $queryBuilder
            ->andWhere($alias.'.parameterGroup = :parameterGroup')
            ->setParameter('parameterGroup', $parameterGroup)
            ->join($alias.'.productVariant', 'pv')
            ->join('pv.product', 'pr')
            ->join('pr.categoryProducts', 'cp')
            ->andWhere('cp.category IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryids)
            ->groupBy($alias.'.data');
    }

    public function getParameterByDataAndProductVariant(ParameterGroup|string $parameterGroup, ProductVariant $productVariant): Parameter|null
    {
        $qb = $this->createQueryBuilder("p")
            ->leftJoin("p.parameterGroup", "pg");
        $qb->andWhere("p.productVariant = :productVariant")->setParameter("productVariant", $productVariant);

        if (is_string($parameterGroup)) {
            $qb->andWhere("pg.name LIKE :parameterGroup")
                ->setParameter("parameterGroup", "%" . $parameterGroup . "%");
        } else {
            $qb->andWhere("pg.id = :parameterGroup")
                ->setParameter("parameterGroup", $parameterGroup->getId());
        }
        $qb->groupBy("p.data");
        return $qb->getQuery()->getOneOrNullResult();
    }


    public function findProductParameterGroups(Product $product, array|null $commonParameterGroups = null)
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.parameterGroup', 'pg')
            ->andWhere('pg.isProductParameter = 1')
            ->andWhere('p.productVariant in (:productVariants)')
            ->setParameter('productVariants', $product->getProductVariants());

            if ($commonParameterGroups != null){
                $qb->andWhere('p.parameterGroup not in (:pgarray)')->setParameter('pgarray', $commonParameterGroups)
                    ->groupBy("pg.id");
            }
            $qb->groupBy('p.parameterGroup');

            return $qb->getQuery()->getResult();
    }

    /*
     * Returns array of unique parameters
     */
    public function findCommonParameters(Product $product): array
    {
        $parameterGroups = $this->createQueryBuilder('p')
            ->leftJoin('p.parameterGroup', 'pg')
            ->andWhere('pg.isProductParameter = true')
            ->andWhere('p.productVariant in (:variants)')
            ->setParameter('variants', $product->getProductVariants())
            ->select('pg.id')
            ->distinct(true)
            ->getQuery()->getResult();

        $parameterGroups = array_column($parameterGroups, 'id');

        /*
         * Check if the parameter is same for all variants and add common parameters to return array
         */
        $returnArray = [];
        foreach ($parameterGroups as $groupId) {
            $groupedResult = $this->createQueryBuilder('p')
                ->leftJoin('p.parameterGroup', 'pg')
                ->andWhere('pg.isProductParameter = true')
                ->andWhere('p.productVariant in (:variants)')
                ->setParameter('variants', $product->getProductVariants())
                ->andWhere('p.parameterGroup = :groupid')
                ->setParameter('groupid', $groupId)
                ->groupBy('p.data')
                ->select('p')
                ->addSelect('COUNT(p.id)')
                ->getQuery()->getResult();

            if (count($groupedResult) == 1 and $groupedResult[0][1] >= $product->getProductVariants()->count()) {
                $returnArray[] = $groupedResult[0][0];
            }
        }
        return $returnArray;
    }

    public function getSingleParameterByParameterGroupForCategory(string $parameterGroupName, Category $category): Parameter|null
    {
        try {
            $qb = $this->createQueryBuilder("p")
                ->leftJoin("p.parameterGroup", "pg")
                ->andWhere("pg.name LIKE :parameterGroup")
                ->setParameter("parameterGroup", "%" . $parameterGroupName . "%")
                ->andWhere("p.category = :category")->setParameter("category", $category)
                ->getQuery()->getSingleResult();
        } catch (NoResultException $exception) {
            $qb = null;
        }
        return $qb;
    }
}
