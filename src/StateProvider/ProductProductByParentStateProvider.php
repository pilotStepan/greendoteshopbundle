<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Dto\ProductVariantPriceContext;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\ProductProductRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductProductByParentStateProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: ItemProvider::class)] private ProviderInterface $itemProvider,
        private CalculatedPricesService     $calculatedPricesService,
        private ProductProductRepository    $productProductRepository,
        private ProductRepository           $productRepository,
        private CurrencyManager             $currencyManager,

    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {

        $parentProductId = $uriVariables['id'];
        $parentProduct = $this->productRepository->find($parentProductId);
        $productProducts = $this->productProductRepository->findBy(['parentProduct'=>$parentProductId]);

        $context = new ProductVariantPriceContext(
            currencyOrConversionRate: $this->currencyManager->get(),
            parentProduct: $parentProduct,
        );
        foreach($productProducts as $productProduct) {
            $productProduct->setChildrenProduct( $this->calculatedPricesService->makeCalculatedPricesForProductWithVariants($productProduct->getChildrenProduct(), $context));
        }

        return $productProducts;
    }
}