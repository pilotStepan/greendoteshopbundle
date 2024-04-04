<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\Product;
use App\Service\CategoryInfoGetter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry             $registry,
        PriceRepository             $priceRepository,
        private CategoryRepository  $categoryRepository,
        private CategoryInfoGetter  $categoryInfoGetter,
        private ParameterRepository $parameterRepository

    )
    {
        parent::__construct($registry, Product::class);
        $this->priceRepository = $priceRepository;
    }

    public function findActive()
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :val')
            ->setParameter('val', 1)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCategoryProducts(Category $category)
    {
        $qb = $this->createQueryBuilder('p');

        $qb->join('p.categoryProducts', 'c');

        $qb->setParameter('val', 1);

        $subIds = [];
        $subIds[] = $category->getId();

        if ($category->getCategoryCategories()) {
            $subCategories = $category->getCategoryCategories();
            foreach ($subCategories as $subCatCategory) {
                $subCategory = $subCatCategory->getCategorySub();
                $subIds[] = $subCategory->getId();
            }
        }
        $qb->andWhere('c.category in (:subIds)');
        $qb->setParameter('subIds', $subIds);
        $qb->andWhere('p.isActive = :val');
        $qb->distinct();
        $qb->orderBy('p.sequence', 'ASC');
        return $qb->getQuery()->getResult();
    }

    public function findCategoryProductsQB(int $category, QueryBuilder $qb)
    {
        $alias = $qb->getRootAliases()[0];
        $category = $this->categoryRepository->find($category);
        $qb->join($alias . '.categoryProducts', 'c');

        $qb->setParameter('val', 1);

        $allSubCats = $this->categoryInfoGetter->getAllSubCategories($category);
        $subIds = [];
        foreach ($allSubCats as $subCat) {
            $subIds[] = $subCat->getId();
        }
        $qb->andWhere('c.category in (:subIds)');
        $qb->setParameter('subIds', $subIds);
        $qb->andWhere($alias . '.isActive = :val');
        $qb->distinct();
        return $qb;
    }

    public function productsByParameterQB(QueryBuilder $queryBuilder, array $parameters)
    {

        $paramDataArray = [];
        $parameterGroup = null;
        foreach ($parameters as $parameter) {
            $parameter = $this->parameterRepository->find($parameter);
            $paramDataArray []= $parameter->getData();
            $parameterGroup = $parameterGroup ?? $parameter->getParameterGroup();
        }


        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->join($alias . '.productVariants', 'prodV')
            ->join('prodV.parameters', 'params')
            ->andWhere('params.data in (:paramData)')->setParameter('paramData', $paramDataArray)
            ->andWhere('params.parameterGroup = :group')->setParameter('group', $parameterGroup);

        //dd($queryBuilder->getQuery());


    }

    public function findCategoryNewProducts(Category $category, int $max = 4)
    {
        $qb = $this->createQueryBuilder('p');

        $qb->join('p.categoryProducts', 'c');
        $qb->join('p.productVariants', 'v');

        $qb->setParameter('val', 1);

        $subIds = [];
        array_push($subIds, $category->getId());

        if ($category->getCategoryCategories()) {
            $subCategories = $category->getCategoryCategories();
            foreach ($subCategories as $subCatCategory) {
                $subCategory = $subCatCategory->getCategorySub();
                array_push($subIds, $subCategory->getId());
            }
        }
        $qb->andWhere('c.category in (:subIds)');
        $qb->setParameter('subIds', $subIds);
        $qb->andWhere('p.isActive = :val');
        $qb->andWhere('v.id is not null');
        $qb->distinct();
        $qb->orderBy('p.id', 'DESC');
        $qb->setMaxResults($max);

        return $qb->getQuery()->getResult();
    }

    public function findProductsWithExternalIDs($limit, $offset)
    {
        return $this->createQueryBuilder("p")
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->andWhere("p.externalId is not null")
            ->getQuery()->getResult();
    }

    public function findAllWithLimit($limit, $offset)
    {
        return $this->createQueryBuilder('p')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()->getResult();
    }

    public function findProductsWithDiscountForAPI($queryBuilder, \DateTime $date, int $minimalAmount = 1, int|null $vat = null)
    {
        $alias = $queryBuilder->getAllAliases()[0];
        $queryBuilder
            ->leftJoin($alias . '.productVariants', 'pv')
            ->leftJoin('pv.price', 'p')
            ->andWhere('p.validFrom <= :date')
            ->setParameter('date', $date)
            ->andWhere(
                $queryBuilder->expr()->orX(
                    'p.validUntil >= :date',
                    'p.validUntil IS NULL'
                )
            )
            ->andWhere('p.minimalAmount <= :minAmount')
            ->setParameter('minAmount', $minimalAmount)
            ->andWhere('p.discount > 0')
            ->orderBy('p.discount', 'DESC')
            ->addOrderBy('p.price', 'ASC')
            ->groupBy('p.productVariant');
        if ($vat) {
            $queryBuilder->andWhere('p.vat = :vat')->setParameter('vat', $vat);
        }
        return $queryBuilder;
    }


    public function sortProductsByPrice($queryBuilder, \DateTime $date, string $sort, int $minimalAmount = 1, int|null $vat = null)
    {
        $alias = $queryBuilder->getAllAliases()[0];

        if (strtoupper($sort) == "DESC") {
            $defaultPrice = 0;
        } elseif (strtoupper($sort) == "ASC") {
            $defaultPrice = 2147483640;
        } else {
            $defaultPrice = 0;
        }

        $subquery = $this->_em->createQueryBuilder()
            ->select('CASE WHEN MIN(pv_price.price) IS NULL THEN :defaultPrice ELSE MIN(pv_price.price) END')
            //->select('MIN(pv_price.price)')
            ->from('App:Project\ProductVariant', 'pv')
            ->leftJoin('pv.price', 'pv_price', Join::WITH,
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq('pv_price.minimalAmount', $minimalAmount),
                    $queryBuilder->expr()->lte('pv_price.validFrom', ':currentDateTime'),
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->isNull('pv_price.validUntil'),
                        $queryBuilder->expr()->gte('pv_price.validUntil', ':currentDateTime')
                    ),
                )
            )
            ->andWhere('pv.product = ' . $alias)
            ->andWhere('pv_price.price IS NOT NULL')
            ->andWhere('pv.product IS NOT NULL')
            ->getQuery();


        $queryBuilder
            ->addSelect('(' . $subquery->getDQL() . ') AS HIDDEN min_price')
            ->setParameter('defaultPrice', $defaultPrice) // Set a suitable default value
            ->setParameter('currentDateTime', $date)
            ->orderBy('min_price', strtoupper($sort));

        return $queryBuilder;
    }

    //zaloha
    public function sortProductsByPrice2($queryBuilder, \DateTime $date, string $sort, int $minimalAmount = 1, int|null $vat = null)
    {
        $alias = $queryBuilder->getAllAliases()[0];
        $queryBuilder
            ->leftJoin($alias . '.productVariants', 'pv')
            ->leftJoin('pv.price', 'p')
            ->andWhere('p.validFrom <= :date')
            ->setParameter('date', $date)
            ->andWhere(
                $queryBuilder->expr()->orX(
                    'p.validUntil >= :date',
                    'p.validUntil IS NULL'
                )
            )
            ->andWhere('p.minimalAmount <= :minAmount')
            ->setParameter('minAmount', $minimalAmount)
            ->addOrderBy('p.price', strtoupper($sort))
            ->groupBy('pv')//->groupBy('p.productVariant')
        ;
        if ($vat) {
            $queryBuilder->andWhere('p.vat = :vat')->setParameter('vat', $vat);
        }
        return $queryBuilder;
    }


    public function findProductWithPhotoForCategory(Category $category): ?Product
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.categoryProducts', 'cp')
            ->leftJoin('cp.category', 'c')
            ->andWhere('c.id = :category')->setParameter('category', $category->getId())
            ->andWhere('p.upload is not null')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    public function findProductsLLG(){
        return $this->createQueryBuilder('p')
            ->select('p.id')
            ->leftJoin('p.producer', 'producer')
            ->andWhere('producer.name = :producerName')->setParameter('producerName', 'LLG')
            ->andWhere('p.externalId is not null')
            ->getQuery()->getResult();
    }

    public function getAllExternalIDsOfProducts(){
        $externalId =  $this->createQueryBuilder('p')
            ->select('p.externalId')
            ->leftJoin('p.producer', 'producer')
            ->andWhere('producer.name = :producerName')->setParameter('producerName', 'LLG')
            ->andWhere('p.externalId is not null')
            ->getQuery()->getResult();

        return array_column($externalId, 'externalId');
    }

    // /**
    //  * @return Product[] Returns an array of Product objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Product
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
