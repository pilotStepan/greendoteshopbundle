<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Doctrine\Orm\Paginator as ApiPlatformPaginator;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

readonly class ProductStateProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository  $productRepository,
        private CategoryRepository $categoryRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        $filters = json_decode($context['filters']['parameters']);

        $qb = $this->productRepository->createQueryBuilder('p')
                ->andWhere('p.isVisible = :visible')
                ->setParameter('visible', true);  
        if ($filters->categoryId > 0) {
            $this->productRepository->findProductsInCategory($qb, $filters->categoryId);
        }
        if (count($filters->supplierIds) > 0) {
            $this->productRepository->findProductsForProducers($qb, $filters->supplierIds);
        }
        if (isset($filters->productViewTypes) && count($filters->productViewTypes) > 0) {
            $qb->andWhere('p.productViewType in (:productViewTypes)')
                ->setParameter('productViewTypes', $filters->productViewTypes)
            ;
        }
        if (isset($filters->discounts) && $filters->discounts) {
            $this->productRepository->findDiscountedProducts($qb);
        }
        $this->productRepository->productsByParameters($qb, $filters->selectedParameters);

        if ($filters->isStockOnly) {
            $this->productRepository->sortProductsByAvailability($qb);
        }
        switch ($filters->orderBy->id) {
            case 'name':
                if ($filters->orderBy->direction === 'DESC') {
                    $qb->orderBy('p.name', 'DESC');
                } else {
                    $qb->orderBy('p.name', 'ASC');
                }
                break;
            case 'price':
                $this->productRepository->sortProductsByPrice($qb, new \DateTime(), $filters->orderBy->direction);
                break;
            case 'rating':
                $this->productRepository->sortProductsByReviews($qb, $filters->orderBy->direction);
                break;
            default:
                if ($filters?->categoryId && $filters->categoryId > 0) {
                    $category = $this->categoryRepository->find($filters->categoryId);
                    if ($category->getCategoryCategories()->count() == 0) {
                        $qb->orderBy('cp.sequence', 'ASC');
                        break;
                    }
                }
                $this->productRepository->sortProductsBySequence($qb, 'DESC');
                break;
        }


        // limit and offset
        $limit = $filters->itemsPerPage ?? 30;
        $page = $filters->page ?? 1;
        $offset = ($page - 1) * $limit;
        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);
        $doctrinePaginator = new DoctrinePaginator($qb->getQuery(), true);
        return new ApiPlatformPaginator($doctrinePaginator);
    }
}