<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductItemStateProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: ItemProvider::class)] private ProviderInterface $itemProvider,
        private CalculatedPricesService $calculatedPricesService,

    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $product =  $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$product instanceof Product) {
            return $product;
        }

        $product = $this->calculatedPricesService->makeCalculatedPricesForProductWithVariants($product);

        return $product;
    }
}