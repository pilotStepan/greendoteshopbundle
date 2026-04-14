<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Upload;
use Greendot\EshopBundle\Enum\UploadGroupTypeEnum;
use Greendot\EshopBundle\Repository\Utils\SafeJoin;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use DateTime;
use Greendot\EshopBundle\Repository\HintedRepositoryBase;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Availability;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends HintedRepositoryBase
{
    use SafeJoin;

    public function __construct(
        ManagerRegistry                     $registry,
        private readonly CategoryRepository $categoryRepository,
        private readonly CategoryInfoGetter $categoryInfoGetter,
        RequestStack                        $requestStack,
    )
    {
        parent::__construct($registry, Product::class, $requestStack);
    }

    public function findProductUploadSubstitute(Product $product): ?Upload
    {
        $qb = $this->getEntityManager()->getRepository(Upload::class)->createQueryBuilder('u');


        $qb
            ->leftJoin('u.uploadGroup', 'ug')
            ->leftJoin('ug.productUploadGroups', 'pug')
            ->leftJoin('ug.productVariantUploadGroups', 'pvug')
            ->leftJoin('pvug.ProductVariant', 'pv')
            ->where(
                $qb->expr()->orX(
                    'pug.Product = :product',
                    'pv.product = :product'
                )
            )
            ->andWhere('ug.type = :type')
            ->orderBy('u.sequence', 'ASC')
            ->setParameter('product', $product)
            ->setParameter('type', UploadGroupTypeEnum::IMAGE)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByArrayOfIdsQB(QueryBuilder $queryBuilder, array $ids): QueryBuilder
    {
        $alias = $queryBuilder->getAllAliases()[0];
        $queryBuilder->andWhere($alias . '.id IN (:ids)');
        $queryBuilder->setParameter('ids', $ids);
        return $queryBuilder;
    }

    public function calculateParameters(Product $product): array
    {
        $parameterRepository = $this->getEntityManager()->getRepository(Parameter::class);

        $parametersQB = $parameterRepository->getParametersForProductQB($product->getId());
        $parametersQB
            ->leftJoin('parameter.parameterGroup', 'parameterGroup')
            ->select('parameter.data, parameterGroup.name')
        ;

        return $parametersQB->getQuery()->getArrayResult();
    }

    public function findProductsByProducer(int $producerId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.producer', 'pr')
            ->andWhere('pr.id = :producerId')
            ->setParameter('producerId', $producerId)
            ->andWhere('p.isActive = 1')
            ->orderBy('p.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @deprecated Use function in AvailabilityRepository directly
     *
     * @param Product $product
     * @return Availability|null
     */
    public function findAvailabilityByProduct(Product $product): ?Availability
    {
        return $this->getEntityManager()->getRepository(Availability::class)->getAvailabilityForProduct($product->getId());
    }

    public function findTopSellingProducts(array $products, int $limit): array
    {
        $productIds = array_map(fn($product) => $product->getId(), $products);

        $qb = $this->createQueryBuilder('p')
            ->select('p, COUNT(ppv.id) AS variantCount' /*u.path AS imagePath'*/)
            ->join('p.productVariants', 'pv')
            ->join('pv.orderProductVariants', 'ppv')
            // ->leftJoin('p.upload', 'u') //
            ->where('p.id IN (:productIds)')
            ->setParameter('productIds', $productIds)
            ->groupBy('p.id, u.path')
            ->orderBy('variantCount', 'DESC')
            ->setMaxResults($limit);

        $result = $qb->getQuery()->getResult();
        $topProducts = array_map(fn($row) => $row[0], $result);
        return $topProducts;

        /**
         * ProductEventListener->postLoad() already does this
         */
        // foreach ($result as $row) {
        //     $product = $row[0];
        //     $imagePath = $row['imagePath'];

        //     $availabilityCheckQb = $this->createQueryBuilder('p')
        //         ->select('COUNT(pv.id)')
        //         ->join('p.productVariants', 'pv')
        //         ->where('p.id = :productId')
        //         ->andWhere('pv.availability = 1')
        //         ->setParameter('productId', $product->getId());

        //     $hasAvailability = $availabilityCheckQb->getQuery()->getSingleScalarResult() > 0;

        //     $product->setAvailability($hasAvailability ? 'Skladem' : 'Vyprodáno');
        //     $product->setImagePath($imagePath);

        //     $topProducts[] = $product;
        // }

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

    public function findCategoryProducts(Category $category, $limit = null)
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
        if($limit !== null){
            $qb->setMaxResults($limit);
        }
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

    /*
     * TODO remove
     */
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

    public function sortProductsByReviews(QueryBuilder $qb, $direction = 'DESC'): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];

        $qb
            ->leftJoin($alias . '.reviews', 'r')
            ->addSelect('AVG(r.stars) AS HIDDEN avg_rating')
            ->groupBy($alias . '.id')
            ->addOrderBy('avg_rating', $direction);

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

    /*
     * Add to statement with joined variants.
     */
    public function filterAvailableQB(QueryBuilder $qb): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];

        $qb
            ->innerJoin('pv.availability', 'a')
            ->andWhere($alias . '.state = :state')
            ->andWhere('pv.isActive = :variantActive')
            ->andWhere('a.id = :availabilityId')
            ->setParameter('state', 'active')
            ->setParameter('variantActive', true)
            ->setParameter('availabilityId', 1);

        return $qb;
    }


    public function sortProductsByAvailability(QueryBuilder $qb){
        $this->safeJoin($qb, 'p', 'productVariants', 'pv'); 
        $this->safeJoin($qb, 'pv', 'availability', 'a');
        $qb->addSelect("MIN(a.sequence) AS HIDDEN min_sequence")
            ->groupBy('p.id')
            ->addOrderBy('min_sequence', 'ASC');
    }

    public function findDiscountedProducts(QueryBuilder $qb, DateTime $date = new \DateTime)
    {
        $this->safeJoin($qb, 'p', 'productVariants', 'pv', 'inner'); 
        $this->safeJoin($qb, 'pv', 'price', 'price');
        
        $qb ->andWhere('price.validFrom <= :date')
            ->andWhere('price.validUntil >= :date OR price.validUntil IS NULL')
            ->andWhere('price.discount IS NOT NULL AND price.discount > 0')
            ->setParameter('date', $date);
    }           

    public function findByDiscountQB(QueryBuilder $qb): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];


        
        $this->safeJoin($qb, $alias, 'productVariants', 'pv', 'inner');
        $this->safeJoin($qb, 'pv', 'price', 'price', 'inner');


        $qb ->andWhere('p.discount IS NOT NULL')
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

        $categoryIds = $this->categoryRepository->findAllChildCategoryIds($categoryId);

        $this->safeJoin($qb, $alias, 'categoryProducts', 'cp');
        $qb->andWhere('cp.category IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds);

        //$this->safeJoin($qb, $alias, 'categoryProducts', 'cp');
        //$this->safeJoin($qb, 'cp', 'category', 'ca');
        //$this->safeJoin($qb, 'ca', 'categorySubCategories', 'cc');
        //$qb->andWhere('cp.category = :categoryId OR cc.category_super = :categoryId');
        //$qb->setParameter('categoryId', $categoryId);

        return $qb;
    }

    public function findProductsForProducers(QueryBuilder $qb, iterable $producers): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];

        $qb->andWhere($alias . '.producer IN (:producers)');
        $qb->setParameter('producers', $producers);

        return $qb;
    }

    public function productsByParameters(QueryBuilder $queryBuilder, iterable $parameters): QueryBuilder
    {

        $alias = $queryBuilder->getRootAliases()[0];
        $this->safeJoin($queryBuilder, $alias, 'productVariants', 'pv'); 
        
        $i = 1;
        foreach ($parameters as $parameter) {
            if($parameter['parameterGroup']['id'] === 'price'){
                // Join prices for price filtering
                $this->safeJoin($queryBuilder, 'pv', 'price', 'price');

                // TODO: maybe based on something different?
                // now it works as a property of price parameterGroup that is set in vue (productBase/category)
                $minPriceCalculation = ($parameter['parameterGroup']['withVat'] ?? false) ?
                    'price.price * (1 + COALESCE(price.vat, 0) / 100) * (1 - COALESCE(price.discount, 0) / 100 )' :
                    'price.price';

                // Apply range filter using MIN($minPriceCalculation)
                $queryBuilder
                    ->andWhere('price.validFrom <= :date')
                    ->andWhere('price.validUntil >= :date OR price.validUntil IS NULL')
                    ->andWhere("price.minimalAmount = 1")
                    ->addSelect("MIN({$minPriceCalculation}) AS hidden priceFilter_minPrice")
                    ->groupBy('p')
                    ->andHaving("priceFilter_minPrice BETWEEN :minPrice AND :maxPrice")
                    ->setParameter('minPrice', (float)$parameter['selectedParameters'][0]-1) // expected: [min, max], correction for rounding error
                    ->setParameter('maxPrice', (float)$parameter['selectedParameters'][1]+1)
                    ->setParameter('date', new \DateTime());

            }
            else{
                $queryBuilder
                    ->innerJoin('pv.parameters', 'pa'.$i)
                    ->innerJoin('pa'.$i.'.parameterGroup', 'pg'.$i)
                    ->andWhere("pg$i.id = :pg".$i."id")
                    ->setParameter("pg".$i."id", $parameter['parameterGroup']['id']);

                if ($parameter['parameterGroup']['parameterGroupFilterType']['name'] == "range") {
                    $alias = "pa".$i;
                    $floatData = "CAST(REPLACE($alias.data, ',', '.') AS DOUBLE)";
                    $queryBuilder
                        ->andWhere("$floatData BETWEEN :minVal$i AND :maxVal$i")
                        ->setParameter("minVal$i", (float)$parameter['selectedParameters'][0])
                        ->setParameter("maxVal$i", (float)$parameter['selectedParameters'][1]);
                } else {
                    // andWhere uses ?1, ?2 etc. matching your $i
                    $queryBuilder->andWhere("pa$i.data IN (?$i)")
                                ->setParameter($i, $parameter['selectedParameters']);
                }
                $i++;
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

        $this->safeJoin($queryBuilder, $alias, 'productVariants', 'pv', 'left');
        $this->safeJoin($queryBuilder, 'pv', 'price', 'price', 'left');

        $queryBuilder
            ->andWhere('price.validFrom <= :date')
            ->setParameter('date', $date)
            ->andWhere(
                $queryBuilder->expr()->orX(
                    'price.validUntil >= :date',
                    'price.validUntil IS NULL'
                )
            )
            ->andWhere('price.minimalAmount <= :minAmount')
            ->setParameter('minAmount', $minimalAmount)
            ->andWhere('price.discount > 0')
            ->orderBy('price.discount', 'DESC')
            ->addOrderBy('price.price', 'ASC')
            ->groupBy('price.productVariant');
        if ($vat) {
            $queryBuilder->andWhere('price.vat = :vat')->setParameter('vat', $vat);
        }
        return $queryBuilder;
    }


    public function sortProductsByPrice(QueryBuilder $qb, DateTime $date, string $direction) : QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];
        $this->safeJoin($qb, $alias, "productVariants", 'pv', 'left');
        $this->safeJoin($qb, 'pv', 'price', 'price', 'left');

        $qb ->andWhere('price.validFrom <= :date')
            ->andWhere('price.validUntil >= :date OR price.validUntil IS NULL')
            ->setParameter('date', $date)
            ->groupBy('p')
            ->addSelect('MIN(price.price) AS hidden minPrice')
            ->addOrderBy('minPrice', strtoupper($direction));

        return $qb;
    }

    public function sortProductsBySequence(QueryBuilder $qb, string $direction) : QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];
        $qb->addOrderBy($alias.'.sequence', strtoupper($direction));
        return $qb;
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

    /**
     * Either takes Purchase or Purchase id
     *
     * @param Purchase|int $purchase
     * @return QueryBuilder
     */
    public function findProductsByPurchaseQB(Purchase|int $purchase): QueryBuilder
    {
        if($purchase instanceof Purchase){
            $purchase = $purchase->getId();
        }

        return $this->createQueryBuilder('product')
            ->innerJoin('product.productVariants', 'productVariant')
            ->innerJoin('productVariant.orderProductVariants', 'purchaseProductVariant')
            ->where('purchaseProductVariant.purchase = :purchase')
            ->setParameter('purchase', $purchase);

    }


    public function mainProductsFilter(array $filters, bool $count = false): QueryBuilder
    {
        if (!isset($filters['categoryId'])) $filters['categoryId'] = 0;

        if (!isset($filters['supplierIds'])) $filters['supplierIds'] = [];
        if (!isset($filters['productViewTypes'])) $filters['productViewTypes'] = [];
        if (!isset($filters['selectedParameters'])) $filters['selectedParameters'] = [];

        if (!isset($filters['discounts'])) $filters['discounts'] = false;
        if (!isset($filters['isStockOnly'])) $filters['isStockOnly'] = false;


        $availableOrderByIds = ['name', 'price', 'rating', 'default'];

        if (!isset($filters['orderBy'])){
            $filters['orderBy'] = ['id' => '', 'direction' => 'DESC'];
        }else{
            $orderBy = $filters['orderBy'];
            $cleanOrderBy = [];
            if (!isset($orderBy['id']) or !in_array($orderBy['id'], $availableOrderByIds)){
                $cleanOrderBy['id'] = 'default';
            } else {
                $cleanOrderBy['id'] = $orderBy['id'];
            }
            if (!isset($orderBy['direction']) or !in_array($orderBy['direction'], ['ASC', 'DESC']) ){
                $cleanOrderBy['direction'] = 'DESC';
            } else {
                $cleanOrderBy['direction'] = $orderBy['direction'];
            }
            $filters['orderBy'] = $cleanOrderBy;
        }

        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isVisible = :visible')
            ->setParameter('visible', true)
        ;
        if ($count){
            $qb->select("COUNT(p.id)");
        }

        if ($filters['categoryId'] > 0) {
            $this->findProductsInCategory($qb, $filters['categoryId']);
        }

        if (count($filters['supplierIds']) > 0) {
            $this->findProductsForProducers($qb, $filters['supplierIds']);
        }

        if (count($filters['productViewTypes']) > 0) {
            $qb->andWhere('p.productViewType in (:productViewTypes)')
                ->setParameter('productViewTypes', $filters['productViewTypes'])
            ;
        }

        if ($filters['discounts']) {
            $this->findDiscountedProducts($qb);
        }

        if (count($filters['selectedParameters']) > 0) {
            $this->productsByParameters($qb, $filters['selectedParameters']);
        }

        if ($count){
            return $qb;
        }

        //this DOES NOT filter out only inStock product, but sorts them at the end
        if ($filters['isStockOnly']) {
            $this->sortProductsByAvailability($qb);
        }

        $direction = $filters['orderBy']['direction'];
        switch ($filters['orderBy']['id']) {
            case 'name':
                $qb->orderBy('p.name', $direction);
                break;
            case 'price':
                $this->sortProductsByPrice($qb, new \DateTime(), $direction);
                break;
            case 'rating':
                $this->sortProductsByReviews($qb, $direction);
                break;
            default:
                if ($filters['categoryId'] > 0) {
                    $qb->addOrderBy('cp.sequence', 'ASC');
                    break;
                }
                $this->sortProductsBySequence($qb, 'DESC');
                break;
        }


        $qb->addOrderBy('p.id', 'DESC');

        $limit = isset($filters['itemsPerPage']) ? (int)$filters['itemsPerPage'] : 30;
        if ($limit <= 0) {
            $limit = 30;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        if ($page <= 0) {
            $page = 1;
        }

        $offset = ($page - 1) * $limit;
        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);


        return $qb;
    }

}
