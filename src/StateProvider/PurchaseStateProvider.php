<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

readonly class PurchaseStateProvider implements ProviderInterface
{
    public function __construct(
        private PurchaseRepository      $purchaseRepository,
        private ManagePurchase          $managePurchase,
        private CalculatedPricesService $calculatedPricesService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Purchase
    {
        $purchase = $this->purchaseRepository->findOneBySession('purchase');
        if (!$purchase) {
            throw new HttpException(Response::HTTP_NO_CONTENT, 'No purchase in session');
        }

        $this->managePurchase->preparePrices($purchase);
        $this->calculatedPricesService->makeCalculatedPricesForPurchaseWithVariants($purchase);
        return $purchase;
    }
}