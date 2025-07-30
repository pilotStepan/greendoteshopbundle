<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyResolver;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;

readonly class PurchaseStateProvider implements ProviderInterface
{
    public function __construct(
        private PurchaseRepository      $purchaseRepository,
        private ManagePurchase          $managePurchase,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Purchase
    {
        $purchase = $this->purchaseRepository->findOneBySession('purchase');
        if (!$purchase) return null;

        $this->managePurchase->PreparePrices($purchase);

        return $purchase;
    }
}