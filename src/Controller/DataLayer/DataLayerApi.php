<?php

namespace Greendot\EshopBundle\Controller\DataLayer;

use Greendot\EshopBundle\DataLayer\Event\CheckoutFunnelEvent;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\DataLayer\DataLayerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DataLayerApi extends AbstractController
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataLayerManager $dataLayerManager
    ){}

    #[Route('/gtm/read/all', name:'gtm_read_all')]
    public function getAll(): JsonResponse
    {
        return new JsonResponse($this->dataLayerManager->all(), 200);
    }


    #[Route('/gtm/checkout-funnel/{type}', name:'gtm_checkout_funnel')]
    public function checkoutFunnel(string $type, PurchaseRepository $purchaseRepository): JsonResponse
    {
        if (!in_array($type, [CheckoutFunnelEvent::ViewCart, CheckoutFunnelEvent::BeginCheckout, CheckoutFunnelEvent::AddPaymentInfo,CheckoutFunnelEvent::AddShippingInfo])){
            return new JsonResponse(sprintf('Invalid type of: %s', $type), 400);
        }

        $this->eventDispatcher->dispatch(new CheckoutFunnelEvent($purchaseRepository->findOneBySession(), $type));
        return new JsonResponse('ok', 200);
    }


    //todo: add API triggers for GTM events

}