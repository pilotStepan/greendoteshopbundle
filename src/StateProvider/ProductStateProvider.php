<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Doctrine\Orm\Paginator as ApiPlatformPaginator;use Greendot\EshopBundle\Entity\Project\Currency;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\PriceCalculator;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductStateProvider implements ProviderInterface
{

    private RequestStack $requestStack;
    private PriceCalculator $priceCalculator;
    private CurrencyRepository $currencyRepository;
    private ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository,
                                RequestStack       $requestStack,
                                PriceCalculator $priceCalculator,
                                CurrencyRepository $currencyRepository)
    {
        $this->requestStack = $requestStack;
        $this->priceCalculator = $priceCalculator;
        $this->currencyRepository = $currencyRepository;
        $this->productRepository = $productRepository;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {


        $filters = json_decode($context['filters']['parameters']);

        $qb = $this->productRepository->createQueryBuilder('p');
        if($filters->categoryId > 0) {
            $this->productRepository->findProductsInCategory($qb, $filters->categoryId);
        }
        if(count($filters->supplierIds) > 0) {
            $this->productRepository->findProductsForProducers($qb, $filters->supplierIds);
        }
        //if($filters->selectedParameters) {
        $this->productRepository->productsByParameters($qb, $filters->selectedParameters);
        //}
        if($filters->isStockOnly){
            $this->productRepository->filterAvailableQB($qb);
        }
        switch ($filters->orderBy->id){
            case 'name':
                if($filters->orderBy->direction === 'DESC'){
                    $qb->orderBy('p.name', 'DESC');
                }else{
                    $qb->orderBy('p.name', 'ASC');
                }
                break;
            case 'price':
                $this->productRepository->sortProductsByPrice($qb, new \DateTime(), $filters->orderBy->direction);
                break;
            case 'default':
                $this->productRepository->sortByReviewsQB($qb, $filters->orderBy->direction);
        }


        //limit and offset
        $limit = $filters->itemsPerPage ?? 30;
        $page = $filters->page ?? 1;
        $offset = ($page - 1) * $limit;
        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        $doctrinePaginator = new DoctrinePaginator($qb->getQuery(), true);
        return new ApiPlatformPaginator($doctrinePaginator);
//        return $qb->getQuery()->getResult();
    }
}