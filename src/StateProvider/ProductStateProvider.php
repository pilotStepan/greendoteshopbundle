<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Doctrine\Orm\Paginator as ApiPlatformPaginator;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

readonly class ProductStateProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        $rawParameters = $context['filters']['parameters'] ?? null;
        $filters = is_string($rawParameters) ? json_decode($rawParameters, true) : null;

        $qb = $this->productRepository->mainProductsFilter($filters);

        $doctrinePaginator = new DoctrinePaginator($qb->getQuery(), true);
        return new ApiPlatformPaginator($doctrinePaginator);
    }
}