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
        $filters = is_string($rawParameters) ? json_decode($rawParameters) : null;
        if (!is_object($filters)) {
            $filters = (object)[];
        }

        $filters->categoryId = isset($filters->categoryId) ? (int)$filters->categoryId : 0;
        $filters->supplierIds = (array)($filters->supplierIds ?? []);
        $filters->productViewTypes = (array)($filters->productViewTypes ?? []);
        $filters->discounts = (bool)($filters->discounts ?? false);
        $filters->selectedParameters = (array)($filters->selectedParameters ?? []);
        $filters->isStockOnly = (bool)($filters->isStockOnly ?? false);
        $filters->orderBy = is_object($filters->orderBy ?? null) ? $filters->orderBy : (object)[];
        $filters->orderBy->id = (string)($filters->orderBy->id ?? '');
        $filters->orderBy->direction = strtoupper((string)($filters->orderBy->direction ?? 'DESC'));
        if (!in_array($filters->orderBy->direction, ['ASC', 'DESC'], true)) {
            $filters->orderBy->direction = 'DESC';
        }

        $qb = $this->productRepository->createQueryBuilder('p')
            ->andWhere('p.isVisible = :visible')
            ->setParameter('visible', true)
        ;

        if ($filters->categoryId > 0) {
            $this->productRepository->findProductsInCategory($qb, $filters->categoryId);
        }

        if (count($filters->supplierIds) > 0) {
            $this->productRepository->findProductsForProducers($qb, $filters->supplierIds);
        }

        if (count($filters->productViewTypes) > 0) {
            $qb->andWhere('p.productViewType in (:productViewTypes)')
                ->setParameter('productViewTypes', $filters->productViewTypes)
            ;
        }

        if ($filters->discounts) {
            $this->productRepository->findDiscountedProducts($qb);
        }

        if (count($filters->selectedParameters) > 0) {
            $this->productRepository->productsByParameters($qb, $filters->selectedParameters);
        }

        if ($filters->isStockOnly) {
            $this->productRepository->sortProductsByAvailability($qb);
        }

        switch ($filters->orderBy->id) {
            case 'name':
                $qb->orderBy('p.name', $filters->orderBy->direction);
                break;
            case 'price':
                $this->productRepository->sortProductsByPrice($qb, new \DateTime(), $filters->orderBy->direction);
                break;
            case 'rating':
                $this->productRepository->sortProductsByReviews($qb, $filters->orderBy->direction);
                break;
            default:
                if ($filters->categoryId > 0) {
                    $qb->addOrderBy('cp.sequence', 'ASC');
                    break;
                }
                $this->productRepository->sortProductsBySequence($qb, 'DESC');
                break;
        }

        $qb->addOrderBy('p.id', 'DESC');

        $limit = isset($filters->itemsPerPage) ? (int)$filters->itemsPerPage : 30;
        if ($limit <= 0) {
            $limit = 30;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $page = isset($filters->page) ? (int)$filters->page : 1;
        if ($page <= 0) {
            $page = 1;
        }

        $offset = ($page - 1) * $limit;
        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        $doctrinePaginator = new DoctrinePaginator($qb->getQuery(), true);
        return new ApiPlatformPaginator($doctrinePaginator);
    }
}