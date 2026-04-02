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

readonly class ProductStateProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository       $productRepository,
        private CalculatedPricesService $calculatedPricesService,
        private CurrencyManager         $currencyManager,
        private PriceUtils              $priceUtils,
        // private ListenerManager         $listenerManager,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        // $this->listenerManager->disableAll([ProductEventListener::class]);

        $rawParameters = $context['filters']['parameters'] ?? null;
        $filters = is_string($rawParameters) ? json_decode($rawParameters, true) : null;

        //count query
        $productsQuery = $this->productRepository->mainProductsFilter($filters);
        $products = $productsQuery->getQuery()->getResult();

        $products = new \ArrayIterator($products);

        $currency = $this->currencyManager->get();
        $converstionRate = $this->priceUtils->getConversionRate($currency);
        $context = new ProductVariantPriceContext(
            currencyOrConversionRate: $converstionRate
        );

        foreach ($products as $product) {
            $this->calculatedPricesService->makeCalculatedPricesForProduct($product, $context);

            // TODO: handle this in a more optimized way
            // $currencySymbol = $currency->getSymbol();
            // $availability = $this->productRepository->findAvailabilityByProduct($product);
            // $parameters = $this->productRepository->calculateParameters($product);

            // $product->setCurrencySymbol($currencySymbol);
            // $product->setAvailability($availability);
            // $product->setParameters($parameters);
        }


        $doctrinePaginator = new DoctrinePaginator($productsQuery);
        $totalItems = count($doctrinePaginator);


        $doctrinePaginator = new DoctrinePaginator($productsQuery);
        $totalItems = count($doctrinePaginator);

        return new TraversablePaginator(
            $products,
            currentPage: $productsQuery->getFirstResult(),
            itemsPerPage: $productsQuery->getMaxResults(),
            totalItems: $totalItems
        );
    }
}