<?php

namespace Greendot\EshopBundle\Controller\DataLayer;

use Greendot\EshopBundle\DataLayer\Event\CheckoutFunnelEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemEvent;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\DataLayer\DataLayerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gtm', name: 'gtm_')]
class DataLayerApi extends AbstractController
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataLayerManager $dataLayerManager
    ){}

    #[Route('/read/all', name:'read_all')]
    public function getAll(): JsonResponse
    {
        return new JsonResponse($this->dataLayerManager->all(), 200);
    }


    #[Route('/checkout-funnel/{type}', name:'checkout_funnel')]
    public function checkoutFunnel(string $type, PurchaseRepository $purchaseRepository): JsonResponse
    {
        if (!in_array($type, [CheckoutFunnelEvent::ViewCart, CheckoutFunnelEvent::BeginCheckout, CheckoutFunnelEvent::AddPaymentInfo,CheckoutFunnelEvent::AddShippingInfo])){
            return new JsonResponse(sprintf('Invalid type of: %s', $type), 400);
        }

        $purchase = $purchaseRepository->findOneBySession();
        if ($purchase instanceof Purchase){
            $this->eventDispatcher->dispatch(new CheckoutFunnelEvent($purchase, $type));
            return new JsonResponse('ok', 200);
        }

        return new JsonResponse('No purchase in session to assemble gtm object.', 400);
    }

    #[Route('/view-item/{id}', name: 'view_item')]
    public function viewItem(
        ProductVariant $productVariant,
    ): JsonResponse
    {
        $this->eventDispatcher->dispatch(new ViewItemEvent($productVariant));
        return new JsonResponse('ok', 200);
    }


    //todo: add API triggers for GTM events

}