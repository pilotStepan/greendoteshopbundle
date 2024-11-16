<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use DateTime;
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

    public function calculateParameters(Product $product): array
    {
        $parameters = [];

        foreach ($product->getProductVariants() as $variant) {
            foreach ($variant->getParameters() as $parameter) {
                $parameters[] = [
                    'name' => $parameter->getParameterGroup()->getName(),
                    'value' => $parameter->getData(),
                ];
            }
        }

        return $parameters;
    }

    public function findAvailabilityByProduct(Product $product): ?string
    {
        $availabilityCheckQb = $this->createQueryBuilder('p')
            ->select('COUNT(pv.id)')
            ->join('p.productVariants', 'pv')
            ->where('p.id = :productId')
            ->andWhere('pv.availability = 1')
            ->setParameter('productId', $product->getId());

        $hasAvailability = $availabilityCheckQb->getQuery()->getSingleScalarResult() > 0;

        return $hasAvailability ? 'skladem' : 'vyprodáno';
    }

    public function findTopSellingProducts(array $products, int $limit): array
    {
        $productIds = array_map(fn($product) => $product->getId(), $products);

        $qb = $this->createQueryBuilder('p')
            ->select('p, COUNT(ppv.id) AS variantCount, u.path AS imagePath')
            ->join('p.productVariants', 'pv')
            ->join('pv.orderProductVariants', 'ppv')
            ->leftJoin('p.upload', 'u') //
            ->where('p.id IN (:productIds)')
            ->setParameter('productIds', $productIds)
            ->groupBy('p.id, u.path')
            ->orderBy('variantCount', 'DESC')
            ->setMaxResults($limit);

        $result = $qb->getQuery()->getResult();

        $topProducts = [];

        foreach ($result as $row) {
            $product = $row[0];
            $imagePath = $row['imagePath'];

            $availabilityCheckQb = $this->createQueryBuilder('p')
                ->select('COUNT(pv.id)')
                ->join('p.productVariants', 'pv')
                ->where('p.id = :productId')
                ->andWhere('pv.availability = 1')
                ->setParameter('productId', $product->getId());

            $hasAvailability = $availabilityCheckQb->getQuery()->getSingleScalarResult() > 0;

            $product->setAvailability($hasAvailability ? 'Skladem' : 'Vyprodáno');
            $product->setImagePath($imagePath);

            $topProducts[] = $product;
        }

        return $topProducts;
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

    public function getProductsForEntity($entity, $onlyActive = true){
        $qb = $this->createQueryBuilder('p');

        if ($onlyActive){
            $qb->andWhere('p.isActive = :val');
            $qb->setParameter('val', 1);
        }

        if ($entity instanceof Category){
            $qb->join('p.categoryProducts', 'c');
            $subIds = [];
            $subIds[] = $entity->getId();

            if ($entity->getCategoryCategories()) {
                $subCategories = $entity->getCategoryCategories();
                foreach ($subCategories as $subCatCategory) {
                    $subCategory = $subCatCategory->getCategorySub();
                    $subIds[] = $subCategory->getId();
                }
            }
            $qb->andWhere('c.category in (:subIds)');
            $qb->setParameter('subIds', $subIds);
        }

        if ($entity instanceof Producer){
            $qb->andWhere('p.producer = :producer');
            $qb->setParameter('producer', $entity);
        }

        if ($entity instanceof Person){
            $qb->leftJoin('p.productPeople', 'pp');
            $qb->andWhere('pp.person = :person');
            $qb->setParameter('person', $entity);
        }

        $qb->distinct();
        $qb->orderBy('p.sequence', 'ASC');
        return $qb->getQuery()->getResult();
    }

    public function findByReviewsQB(QueryBuilder $qb): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];

        $qb
            ->leftJoin($alias . '.reviews', 'r')
            ->addSelect('AVG(r.stars) AS HIDDEN avg_rating')
            ->groupBy($alias . '.id')
            ->orderBy('avg_rating', 'DESC');

        return $qb;
    }

    public function findByAvailabilityQB(QueryBuilder $qb): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];

        $qb
            ->innerJoin($alias . '.productVariants', 'pv')
            ->innerJoin('pv.availability', 'a')
            ->andWhere($alias . '.state = :state')
            ->andWhere('pv.isActive = :variantActive')
            ->andWhere('a.id = :availabilityId')
            ->setParameter('state', 'active')
            ->setParameter('variantActive', true)
            ->setParameter('availabilityId', 1);

        return $qb;
    }

    public function findDiscountedProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.productVariants', 'pv')
            ->innerJoin('pv.price', 'price')
            ->leftJoin('p.upload', 'upload')
            ->andWhere('price.discount IS NOT NULL')
            ->andWhere('price.discount > 0')
            ->addSelect('upload')
            ->getQuery()
            ->getResult();
    }

    public function findByDiscountQB(QueryBuilder $qb): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];

        $qb
            ->innerJoin($alias . '.productVariants', 'pv')
            ->innerJoin('pv.price', 'p')
            ->andWhere('p.discount IS NOT NULL')
            ->andWhere('p.discount > 0');

        return $qb;
    }

    public function findByLabelQB(int $labelId, QueryBuilder $qb): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];

        $qb
            ->innerJoin($alias . '.labels', 'l')
            ->andWhere('l.id = :labelId')
            ->setParameter('labelId', $labelId);

        return $qb;
    }

    /*
     * TODO remove
     */
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

    public function findProductsInCategory(QueryBuilder $qb, int $categoryId): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];


        $qb->join($alias . '.categoryProducts', 'cp');
        $qb->join('p.categories', 'c');
        $qb->leftJoin('c.categoryCategories', 'cc');
        $qb->where('cp.category = :categoryId OR cc.category_super = :categoryId');
        $qb->setParameter('categoryId', $categoryId);


        $qb->andWhere($alias . '.isActive = :val');
        $qb->setParameter('val', 1);

        $qb->distinct();

        return $qb;
    }

    public function productsByParameters(QueryBuilder $queryBuilder, iterable $parameters): QueryBuilder
    {
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->innerJoin($alias . '.productVariants', 'pv')
            ->innerJoin('pv.parameters', 'pa');
        foreach ($parameters as $parameter) {
            if($parameter['parameterGroup']['name'] === 'Cena'){

            }else {
                $queryBuilder->andWhere('pa.data in (:selpar)');
                $queryBuilder->setParameter('selpar', $parameter['selectedParameters']);
            }
        }

        return $queryBuilder;
    }

    /*
     * TODO remove
     */
    public function productsByParameterQB(QueryBuilder $queryBuilder, string $parameter): QueryBuilder
    {
        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->innerJoin($alias . '.productVariants', 'pv')
            ->innerJoin('pv.parameters', 'p')
            ->andWhere('p.data LIKE :parameter')
            ->setParameter('parameter', '%' . $parameter . '%');

        return $queryBuilder;
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

    public function findAllWithLimit($limit, $offset)
    {
        return $this->createQueryBuilder('p')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()->getResult();
    }

    public function findProductsWithDiscountForAPI($queryBuilder, DateTime $date, int $minimalAmount = 1, int|null $vat = null)
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


    public function sortProductsByPrice($queryBuilder, DateTime $date, string $sort, int $minimalAmount = 1, int|null $vat = null)
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

    public function getSoldProductsCount(DateTime $startDate, DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id, SUM(ppv.amount) as sold_amount')
            ->join('Greendot\EshopBundle\Entity\Project\ProductVariant', 'pv', Join::WITH, 'pv.product = p.id')
            ->join('Greendot\EshopBundle\Entity\Project\PurchaseProductVariant', 'ppv', Join::WITH, 'ppv.ProductVariant = pv.id')
            ->join('Greendot\EshopBundle\Entity\Project\Purchase', 'pu', Join::WITH, 'ppv.purchase = pu.id')
            ->where('pu.date_invoiced >= :startDate')
            ->andWhere('pu.date_invoiced <= :endDate')
            ->andWhere('pu.state NOT IN (:excludedStates)')
            ->groupBy('p.id')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('excludedStates', ['draft', 'new']);

        return $qb->getQuery()->getResult();
    }

}
