<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use DateTime;
use Greendot\EshopBundle\Repository\HintedRepositoryBase;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Availability;

/**
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends HintedRepositoryBase
{
    public function __construct(
        ManagerRegistry                     $registry,
        private readonly CategoryRepository $categoryRepository,
        private readonly CategoryInfoGetter $categoryInfoGetter,
    )
    {
        parent::__construct($registry, Product::class);
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


    public function findAvailabilityByProduct(Product $product): ?Availability
    {
        $productAvailability = null;
        foreach ($product->getProductVariants() as $variant) {
            $variantAvailability = $variant->getAvailability();
            if (!$productAvailability || $variantAvailability->getSequence() < $productAvailability->getSequence()){
                $productAvailability = $variantAvailability;
            }
        }
        return $productAvailability;
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

        $category = $this->categoryRepository->findHinted($category);
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
        $this->safeJoin($qb, $alias, 'categoryProducts', 'cp'); 
        $this->safeJoin($qb, 'cp', 'category', 'ca'); 
        $this->safeJoin($qb, 'ca', 'categorySubCategories', 'cc'); 
        $qb->where('cp.category = :categoryId OR cc.category_super = :categoryId');
        $qb->setParameter('categoryId', $categoryId);

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
            if($parameter->parameterGroup->id === 'price'){
                // Join prices for price filtering
                $this->safeJoin($queryBuilder, 'pv', 'price', 'price');

                // TODO: maybe based on something different?
                // now it works as a property of price parameterGroup that is set in vue (productBase/category)
                $minPriceCalculation = ($parameter->parameterGroup->withVat ?? false) ?
                    'price.price * (1 + COALESCE(price.vat, 0) / 100) * (1 - COALESCE(price.discount, 0) / 100 )' :
                    'price.price';

                // Apply range filter using MIN($minPriceCalculation)
                $queryBuilder
                    ->andWhere('price.validFrom <= :date')
                    ->andWhere('price.validUntil >= :date OR price.validUntil IS NULL')
                    ->andWhere("price.minimalAmount = 1")
                    ->addSelect("MIN({$minPriceCalculation}) AS hidden priceFilter_minPrice")
                    ->groupBy('p')
                    ->having("priceFilter_minPrice BETWEEN :minPrice AND :maxPrice")
                    ->setParameter('minPrice', (float)$parameter->selectedParameters[0]-1) // expected: [min, max], correction for rounding error
                    ->setParameter('maxPrice', (float)$parameter->selectedParameters[1]+1)
                    ->setParameter('date', new \DateTime());

            }else {
                $queryBuilder->innerJoin('pv.parameters', 'pa'.$i);
                $queryBuilder->andWhere('pa'.$i.'.data in (?'.$i.')');
                $queryBuilder->setParameter($i++, $parameter->selectedParameters);
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


    public function sortProductsByPrice(QueryBuilder $qb, DateTime $date, string $direction) : QueryBuilder
    {
        $this->safeJoin($qb, 'pv', 'price', 'price');

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
     * Adds a join to the Doctrine QueryBuilder only if it hasn't already been added.
     *
     * This prevents duplicate alias errors in modular code where multiple functions
     * may request the same join.
     *
     * @param QueryBuilder $qb        The Doctrine QueryBuilder instance to modify.
     * @param string       $rootAlias The alias of the root entity (e.g., 'e' for 'FROM Entity e').
     * @param string       $path      The relation path from the root entity (e.g., 'joinedEntity').
     * @param string       $alias     The alias to assign to the joined entity (e.g., 'j').
     * @param string       $joinType  The type of join to perform: 'left' (default) or 'inner'.
     *
     * @throws InvalidArgumentException If an unsupported join type is provided.
     *
     * @example
     * safeJoin($qb, 'e', 'category', 'c');
    */
    function safeJoin(QueryBuilder $qb, string $rootAlias, string $path, string $alias, string $joinType = 'left')
    {

        $joinDqlParts = $qb->getDQLParts()['join'];
        foreach ($joinDqlParts as $joins) {
            foreach ($joins as $join) {
                if ($join->getAlias() === $alias) {
                    return;
                }
            }
        }



        if ($joinType === 'left') {
            $qb->leftJoin("$rootAlias.$path", $alias);
        } elseif ($joinType === 'inner') {
            $qb->innerJoin("$rootAlias.$path", $alias);
        } else {
            throw new \InvalidArgumentException("Unsupported join type: $joinType");
        }
    }


}
