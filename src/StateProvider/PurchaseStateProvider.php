<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;

readonly class PurchaseStateProvider implements ProviderInterface
{
    public function __construct(
        private PurchaseRepository $purchaseRepository,
        private ManagePurchase     $managePurchase,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Purchase
    {
        $purchase = $this->purchaseRepository->findOneBySession('purchase');
        if (!$purchase) return null;

        $this->managePurchase->preparePrices($purchase);

        return $purchase;
    }
}