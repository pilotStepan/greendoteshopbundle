<?php

namespace Greendot\EshopBundle\DataLayer\EventListener;

use Greendot\EshopBundle\DataLayer\Event\AddToWishlistEvent;
use Greendot\EshopBundle\DataLayer\Event\CheckoutFunnelEvent;
use Greendot\EshopBundle\DataLayer\Event\ModifyCart;
use Greendot\EshopBundle\DataLayer\Event\PurchaseEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemListEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemListProductEvent;
use Greendot\EshopBundle\DataLayer\Factory\CartFactory;
use Greendot\EshopBundle\DataLayer\Factory\CheckoutFunnelFactory;
use Greendot\EshopBundle\DataLayer\Factory\PurchaseFactory;
use Greendot\EshopBundle\DataLayer\Factory\ViewItemFactory;
use Greendot\EshopBundle\DataLayer\Factory\ViewItemListFactory;
use Greendot\EshopBundle\DataLayer\Factory\ViewItemListProductFactory;
use Greendot\EshopBundle\DataLayer\Factory\WishlistFactory;
use Greendot\EshopBundle\Service\DataLayer\DataLayerManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class DataLayerEventListener
{
    public function __construct(
        private readonly DataLayerManager      $dataLayerManager,
        private readonly ViewItemListFactory   $viewItemListFactory,
        private readonly ViewItemListProductFactory $viewItemListProductFactory,
        private readonly ViewItemFactory       $viewItemFactory,
        private readonly PurchaseFactory       $purchaseFactory,
        private readonly CartFactory           $cartFactory,
        private readonly CheckoutFunnelFactory $checkoutFunnelFactory,
        private readonly WishlistFactory       $wishlistFactory,
    ) {}

    #[AsEventListener(event: ViewItemListEvent::class)]
    public function onViewItemList(ViewItemListEvent $viewItemList): void
    {
        $eventData = $this->viewItemListFactory->create($viewItemList->getCategory(), $viewItemList->getProductFetchUri(), $viewItemList->getProductIds());
        $this->dataLayerManager->push(['ecommerce' => null], true);
        $this->dataLayerManager->push(['event' => 'view_item_list', 'ecommerce' => $eventData], true);
    }

    #[AsEventListener(event: ViewItemListProductEvent::class)]
    public function onViewItemListProduct(ViewItemListProductEvent $viewItemListProductEvent): void
    {
        $eventData = $this->viewItemListProductFactory->create($viewItemListProductEvent->getProduct());
        $this->dataLayerManager->push(['ecommerce' => null], true);
        $this->dataLayerManager->push(['event' => 'view_item_list_product', 'ecommerce' => $eventData], true);
    }

    #[AsEventListener(event: ViewItemEvent::class)]
    public function onViewItem(ViewItemEvent $viewItemList): void
    {
        $eventData = $this->viewItemFactory->create($viewItemList->getProductVariant());
        $this->dataLayerManager->push(['ecommerce' => null], true);
        $this->dataLayerManager->push(['event' => 'view_item', 'ecommerce' => $eventData], true);
    }

    #[AsEventListener(event: PurchaseEvent::class)]
    public function onPurchase(PurchaseEvent $purchaseEvent): void
    {
        $eventData = $this->purchaseFactory->create($purchaseEvent->getPurchase());
        $this->dataLayerManager->push(['ecommerce' => null], true);
        $this->dataLayerManager->push(['event' => 'purchase', 'ecommerce' => $eventData], true);
    }

    #[AsEventListener(event: ModifyCart::class)]
    public function onCartModified(ModifyCart $modifyCart): void
    {
        if ($modifyCart->getQuantity() === 0) return;

        $eventData = $this->cartFactory->create($modifyCart->getPurchaseProductVariant(), $modifyCart->getQuantity());

        if ($modifyCart->getType() === ModifyCart::Remove){
            $this->dataLayerManager->push(['ecommerce' => null]);
            $this->dataLayerManager->push(['event' => 'remove_from_cart', 'ecommerce' => $eventData], true);
        }else{
            $this->dataLayerManager->push(['event' => 'add_to_cart', 'ecommerce' => $eventData], true);
        }
    }

    #[AsEventListener(event: CheckoutFunnelEvent::class)]
    public function onCheckoutFunnel(CheckoutFunnelEvent $event): void
    {
        $purchase = $event->getPurchase();
        $eventData = match ($event->getType()) {
            CheckoutFunnelEvent::ViewCart   => $this->checkoutFunnelFactory->createViewCart($purchase),
            CheckoutFunnelEvent::BeginCheckout   => $this->checkoutFunnelFactory->createBeginCheckout($purchase),
            CheckoutFunnelEvent::AddPaymentInfo  => $this->checkoutFunnelFactory->createAddPaymentInfo($purchase),
            CheckoutFunnelEvent::AddShippingInfo => $this->checkoutFunnelFactory->createAddShippingInfo($purchase),
        };
        $this->dataLayerManager->push(['ecommerce' => null]);
        $this->dataLayerManager->push(['event' => $event->getType(), 'ecommerce' => $eventData], true);
    }

    #[AsEventListener(event: AddToWishlistEvent::class)]
    public function onAddToWishlist(AddToWishlistEvent $event): void
    {
        $eventData = $this->wishlistFactory->create($event->getPurchaseProductVariant(), $event->getQuantity());
        $this->dataLayerManager->push(['ecommerce' => null], true);
        $this->dataLayerManager->push(['event' => 'add_to_wishlist', 'ecommerce' => $eventData], true);
    }

}