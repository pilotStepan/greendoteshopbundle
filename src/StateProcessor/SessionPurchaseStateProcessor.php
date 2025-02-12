<?php

namespace Greendot\EshopBundle\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\PriceCalculator;
use Symfony\Component\HttpFoundation\RequestStack;

class SessionPurchaseStateProcessor implements ProcessorInterface
{

    private PurchaseRepository $purchaseRepository;
    private RequestStack $requestStack;

    public function __construct(PurchaseRepository $purchaseRepository,
                                RequestStack       $requestStack)
    {
        $this->purchaseRepository = $purchaseRepository;
        $this->requestStack = $requestStack;
    }
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Purchase|null
    {
        $purchase =  $this->purchaseRepository->findOneBySession('purchase');

        if($purchase) {
            return $purchase;
        }else{
            return null;
        }
    }
}
