<?php

namespace Greendot\EshopBundle\Twig;

use Greendot\EshopBundle\DataLayer\Event\CheckoutFunnelEvent;
use Greendot\EshopBundle\DataLayer\Event\PageViewEvent;
use Greendot\EshopBundle\DataLayer\Event\PurchaseEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemListEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemListProductEvent;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\DataLayer\DataLayerManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Attribute\AsTwigFunction;

class GoogleTagManagerExtension
{
    public function __construct(
        private readonly DataLayerManager $dataLayerManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PurchaseRepository $purchaseRepository
    )
    {
    }

    #[AsTwigFunction('gtm_get_events')]
    public function getGtmEvents(): array
    {
        return $this->dataLayerManager->all();
    }

    #[AsTwigFunction('gtm_view_item_list')]
    public function viewItemList(Category $category, ?string $productFetchUri = null, ?array $productIds = null): void
    {
        $this->eventDispatcher->dispatch(new ViewItemListEvent($category, $productFetchUri, $productIds));
    }

    #[AsTwigFunction('gtm_view_item_list_product')]
    public function viewItemListProduct(Product $product): void
    {
        $this->eventDispatcher->dispatch(new ViewItemListProductEvent($product));
    }

    #[AsTwigFunction('gtm_view_item')]
    public function viewItem(ProductVariant $productVariant): void
    {
        $this->eventDispatcher->dispatch(new ViewItemEvent($productVariant));
    }

    #[AsTwigFunction('gtm_purchase')]
    public function purchase(Purchase $purchase): void
    {
        $this->eventDispatcher->dispatch(new PurchaseEvent($purchase));
    }

    #[AsTwigFunction('gtm_page_view')]
    public function pageView(Category $category): void
    {
        $this->eventDispatcher->dispatch(new PageViewEvent($category));
    }

    #[AsTwigFunction('gtm_view_cart')]
    public function viewCart(?Purchase $purchase = null): void
    {$this->checkoutFunnel(CheckoutFunnelEvent::ViewCart, $purchase);}

    #[AsTwigFunction('gtm_begin_checkout')]
    public function beginCheckout(?Purchase $purchase = null): void
    {$this->checkoutFunnel(CheckoutFunnelEvent::BeginCheckout, $purchase);}

    #[AsTwigFunction('gtm_add_payment_info')]
    public function addPaymentInfo(?Purchase $purchase = null): void
    {$this->checkoutFunnel(CheckoutFunnelEvent::AddPaymentInfo, $purchase);}

    #[AsTwigFunction('gtm_add_shipping_info')]
    public function addShippingInfo(?Purchase $purchase = null): void
    {$this->checkoutFunnel(CheckoutFunnelEvent::AddShippingInfo, $purchase);}

    private function checkoutFunnel(string $type, ?Purchase $purchase = null): void
    {
        if (!$purchase){
            $purchase = $this->purchaseRepository->findOneBySession();
        }
        $this->eventDispatcher->dispatch(new CheckoutFunnelEvent($purchase,$type));
    }

}