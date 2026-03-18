<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Doctrine\Orm\Paginator as ApiPlatformPaginator;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

readonly class ProductStateProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository       $productRepository,
        private CalculatedPricesService $calculatedPricesService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        $rawParameters = $context['filters']['parameters'] ?? null;
        $filters = is_string($rawParameters) ? json_decode($rawParameters, true) : null;

        //count query
        $productsQuery = $this->productRepository->mainProductsFilter($filters);
        $products = $productsQuery->getQuery()->getResult();
        

        $products = new \ArrayIterator($products);

        foreach ($products as $products) {
            $this->calculatedPricesService->makeCalculatedPricesForProduct($products);
        }

        return new TraversablePaginator(
            $products,
            currentPage: $productsQuery->getFirstResult(),
            itemsPerPage: $productsQuery->getMaxResults(),
            totalItems: 400
        );
    }
}