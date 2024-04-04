<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Greendot\EshopBundle\Entity\Project\ParameterGroupType;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use App\Service\CategoryInfoGetter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use function Doctrine\ORM\QueryBuilder;

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

    /**
     * @param string $parameterGroupName
     * @param Category $category
     * @return Parameter|null
     */
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

    public function getParameterByDataAndParameterGroup(ParameterGroup|string $parameterGroup, string $data): array
    {
        $qb = $this->createQueryBuilder("p")
            ->leftJoin("p.parameterGroup", "pg");
        $qb->andWhere("p.data = :data")->setParameter("data", $data);

        if (is_string($parameterGroup)) {
            $qb->andWhere("pg.name LIKE :parameterGroup")
                ->setParameter("parameterGroup", "%" . $parameterGroup . "%");
        } else {
            $qb->andWhere("pg.id = :parameterGroup")
                ->setParameter("parameterGroup", $parameterGroup->getId());
        }

        return $qb->getQuery()->getResult();
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
                //this ensures that only unique parameters will be returned
                //parameters where data and parameter_group are the same with every product_variant are printed in separate table (under "technical specifications")

                /*
                 * ToDo: figure out how to print-it out when there is only one product variant
                 */
                $qb->andWhere('p.parameterGroup not in (:pgarray)')->setParameter('pgarray', $commonParameterGroups)
                    ->groupBy("pg.id");
            }
            $qb->groupBy('p.parameterGroup');

            return $qb->getQuery()->getResult();
    }

    public function findVariantParameterByGroup(ProductVariant $productVariant, ParameterGroup $parameterGroup)
    {

        $qb = $this->createQueryBuilder("p")
            ->andWhere("p.parameterGroup = :parameterGroup")
            ->setParameter("parameterGroup", $parameterGroup)
            ->andWhere("p.productVariant = :variant")
            ->setParameter("variant", $productVariant)
            ->getQuery()->getResult();
        return $qb[0] ?? null;
        //return $qb;
    }

    public function findParametersForProductByParameterGroup(Product $product, ParameterGroup $parameterGroup): ?Parameter
    {
        $productVariantIds = [];
        foreach ($product->getProductVariants() as $productVariant) {
            $productVariantIds[] = $productVariant->getId();
        }

        return $this->createQueryBuilder("p")
            ->andWhere('p.productVariant in (:productVariants)')
            ->setParameter('productVariants', $productVariantIds)
            ->andWhere('p.parameterGroup = :parameterGroup')
            ->setParameter('parameterGroup', $parameterGroup)
            ->groupBy('p.data')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    public function findDistinctResultsByParameterGroupTypeForProduct(Product $product, ParameterGroupType $parameterGroupType): array|null
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.parameterGroup', 'pg')
            ->andWhere('pg.type = :type')->setParameter('type', $parameterGroupType)
            ->andWhere('p.productVariant in (:variants)')->setParameter('variants', $product->getProductVariants())
            ->groupBy('p.parameterGroup')->getQuery()->getResult();
    }

    public function findParametersForProduct(Product $product, bool $is_product_parameter = false)
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.productVariant in (:variants)')
            ->setParameter('variants', $product->getProductVariants());

        if ($is_product_parameter) {
            $qb->leftJoin('p.parameterGroup', 'pg')
                ->andWhere('pg.isProductParameter = 1');
        }

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

    public function findParametersForProductByParameterGroupType(Product $product, ParameterGroupType $parameterGroupType)
    {
        return $this->createQueryBuilder('p')
        ->leftJoin('p.parameterGroup', 'pg')
        ->andWhere('pg.type = :group')
        ->setParameter('group', $parameterGroupType)
        ->andWhere('p.productVariant in (:variants)')
        ->setParameter('variants', $product->getProductVariants())
        ->getQuery()->getResult()
        ;

    }

    public function getParametersForProductIndex(ProductVariant $productVariant){
        $results =  $this->createQueryBuilder('p')
            ->select('p.id', 'p.data', 'pg.name, pg.unit')
            ->leftJoin('p.parameterGroup', 'pg')
            ->andWhere('p.productVariant = :variant')->setParameter('variant', $productVariant)
            ->andWhere('pg.isProductParameter = 1 OR pg.name = :groupName')
            ->setParameter('groupName', "Manufacturer")
            ->groupBy('p.data', 'pg.name')
            ->getQuery()->getArrayResult();
        foreach ($results as &$result) {
            if ($result['unit'] === null) {
                unset($result['unit']);
            }
        }

        return $results;
    }

    public function getManufacturerForProductIndex(Product $product){
        if (count($product->getProductVariants()) > 0){
            $productVariant = $product->getProductVariants()[0];
        }else{
            return null;
        }
        $results =  $this->createQueryBuilder('p')
            ->select('p.id', 'p.data', 'pg.name, pg.unit')
            ->leftJoin('p.parameterGroup', 'pg')
            ->andWhere('p.productVariant = :variant')->setParameter('variant', $productVariant)
            ->andWhere('pg.name = :groupName')
            ->setParameter('groupName', "Manufacturer")
            ->groupBy('p.data', 'pg.name')
            ->getQuery()->getArrayResult();
        foreach ($results as &$result) {
            if ($result['unit'] === null) {
                unset($result['unit']);
            }
        }

        return $results;
    }


    /**
     * @param ParameterGroup $parameterGroup
     * @param ProductVariant[] $productVariants
     * @return Parameter[]
     */
    public function getDistinctDataOfParameterGroupForProductVariantArray(ParameterGroup $parameterGroup, $productVariants): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.parameterGroup = :group')->setParameter('group', $parameterGroup)
            ->andWhere('p.productVariant in (:variants)')->setParameter('variants', $productVariants)
            ->groupBy('p.data')
            ->getQuery()->getResult();
    }

    /**
     * @return array
     *
     * returns all external ids for categories in array
     */
    public function getAllCategoryExternalIDs(): array
    {
        $externalIDs =  $this->createQueryBuilder('p')
            ->leftJoin('p.parameterGroup', 'pg')
            ->select('p.data')
            ->andWhere('p.category is not null')
            ->andWhere('pg.name = :groupName')
            ->setParameter('groupName', 'Externí ID')
            ->getQuery()->getResult();

        return array_column($externalIDs, 'data');
    }

//    /**
//     * @return Parameter[] Returns an array of Parameter objects
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

//    public function findOneBySomeField($value): ?Parameter
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
