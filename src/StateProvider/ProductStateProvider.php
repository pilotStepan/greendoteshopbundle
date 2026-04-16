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
use Greendot\EshopBundle\Repository\Project\PriceRepository;

readonly class ProductStateProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository       $productRepository,
        private CalculatedPricesService $calculatedPricesService,
        private CurrencyManager         $currencyManager,
        private PriceUtils              $priceUtils,
        // private ListenerManager         $listenerManager,
        private PriceRepository         $priceRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        // $this->listenerManager->disableAll([ProductEventListener::class]);
        $start= microtime(true);
        $stamps = [];
        $rawParameters = $context['filters']['parameters'] ?? null;
        $filters = is_string($rawParameters) ? json_decode($rawParameters, true) : null;



        //count query
        $productsQuery = $this->productRepository->mainProductsFilter($filters);

        $stamps[] = microtime(true) - $start;

        $doctrinePaginator = new DoctrinePaginator($productsQuery, fetchJoinCollection: true);
        $totalItems = count($doctrinePaginator);
        $products = iterator_to_array($doctrinePaginator);

        $productIds = array_map(fn($p) => $p->getId(), $products);

        // prime products
        $this->productRepository->primeProductList($productIds);

        $stamps[] = microtime(true) - $start;

        $currency = $this->currencyManager->get();
        $converstionRate = $this->priceUtils->getConversionRate($currency);
        $context = new ProductVariantPriceContext(
            currencyOrConversionRate: $converstionRate
        );

        $stamps[] = microtime(true) - $start;

        $cheapestPrices = $this->priceRepository->findCheapestPricesForProducts($productIds);

        $stamps[] = microtime(true) - $start;


        foreach ($products as $product) {
            $this->calculatedPricesService->makeCalculatedPricesForProduct(
                product: $product, context: $context, cheapestPrice: $cheapestPrices[$product->getId()]);

            // TODO: handle this in a more optimized way
            // $currencySymbol = $currency->getSymbol();
            // $availability = $this->productRepository->findAvailabilityByProduct($product);
            // $parameters = $this->productRepository->calculateParameters($product);

            // $product->setCurrencySymbol($currencySymbol);
            // $product->setAvailability($availability);
            // $product->setParameters($parameters);
        }

        $stamps[] = microtime(true) - $start;

        $products = new \ArrayIterator($products);
        $stamps[] = microtime(true) - $start;


        // dd($stamps);
        return new TraversablePaginator(
            $products,
            currentPage: $productsQuery->getFirstResult(),
            itemsPerPage: $productsQuery->getMaxResults(),
            totalItems: $totalItems
        );
    }
}