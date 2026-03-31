<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PurchaseItemStateProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: ItemProvider::class)] private ProviderInterface $itemProvider,
        private CalculatedPricesService $calculatedPricesService,

    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $purchase =  $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$purchase instanceof Purchase) {
            return $purchase;
        }

        $purchase = $this->calculatedPricesService->makeCalculatedPricesForPurchaseWithVariants($purchase);

        return $purchase;
    }
}