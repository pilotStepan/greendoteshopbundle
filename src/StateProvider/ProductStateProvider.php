<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Dto\ProductVariantPriceContext;
use Greendot\EshopBundle\EventSubscriber\ProductEventListener;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\ListenerManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Greendot\EshopBundle\Entity\Project\Availability;
use Greendot\EshopBundle\EventSubscriber\ParameterEventListener;
use Greendot\EshopBundle\EventSubscriber\ProductVariantEventListener;
use Greendot\EshopBundle\Repository\Project\AvailabilityRepository;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Symfony\Component\HttpFoundation\Response;

readonly class ProductStateProvider implements ProviderInterface
{
    public function __construct(
        private ListenerManager         $listenerManager,
        private PriceUtils              $priceUtils,
        private CurrencyManager         $currencyManager,
        private CalculatedPricesService $calculatedPricesService,
        private ProductRepository       $productRepository,
        private AvailabilityRepository  $availabilityRepository,
        private ParameterRepository     $parameterRepository,
        private PriceRepository         $priceRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        $this->listenerManager->disableAll([
            ProductEventListener::class,
            // ProductVariantEventListener::class,
            // ParameterEventListener::class
        ]);
        $start= microtime(true);
        $stamps = [];
        $rawParameters = $context['filters']['parameters'] ?? null;
        $filters = is_string($rawParameters) ? json_decode($rawParameters, true) : null;


        
        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';
        // get all filtered IDs
        $productsQuery = $this->productRepository->mainProductsFilter($filters);
        $allProductIds = $productsQuery->getQuery()->getSingleColumnResult();
        
        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';

        // get count
        $totalItems = count($allProductIds);

        // paginate result
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

        $productIds = array_slice($allProductIds, $offset, $limit);
        
        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';

        // get product entities with initilized associations
        $products = $this->productRepository->primeProductList($productIds);


        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';
        
        $currency = $this->currencyManager->get();
        $converstionRate = $this->priceUtils->getConversionRate($currency);
        $context = new ProductVariantPriceContext(
            currencyOrConversionRate: $converstionRate
        );

        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';

        // this is unoptimized, triggers n+1 query on purchase_product_variant assoc
        $cheapestPriceMap = $this->priceRepository->findCheapestPricesForProducts($productIds);

        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';

        $availabilityMap = $this->availabilityRepository->getAvailabilityForProductIds($productIds);

        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';

        $parametersMap = $this->parameterRepository->calculateParametersForProductIds($productIds);

        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';



        foreach ($products as $product) {
            $this->calculatedPricesService->makeCalculatedPricesForProduct(
                product: $product, context: $context, cheapestPrice: $cheapestPriceMap[$product->getId()]);

            $product->setAvailability($availabilityMap[$product->getId()]);
            $product->setCurrencySymbol = $currency->getSymbol();
            if(array_key_exists($product->getId(), $parametersMap)) $product->setParameters($parametersMap[$product->getId()]);
        }



        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';

        $products = new \ArrayIterator($products);
        $stamps[] = round((microtime(true) - $start) * 1000, 2) . ' ms';


        // return new Response("hello");

        // dd($stamps);
        return new TraversablePaginator(
            $products,
            currentPage: $offset,
            itemsPerPage: $limit,
            totalItems: $totalItems
        );
    }
}