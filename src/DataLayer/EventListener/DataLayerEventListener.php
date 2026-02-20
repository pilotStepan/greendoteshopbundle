<?php

namespace Greendot\EshopBundle\DataLayer\EventListener;

use Greendot\EshopBundle\DataLayer\Event\ModifyCart;
use Greendot\EshopBundle\DataLayer\Event\PurchaseEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemListEvent;
use Greendot\EshopBundle\DataLayer\Factory\CartFactory;
use Greendot\EshopBundle\DataLayer\Factory\PurchaseFactory;
use Greendot\EshopBundle\DataLayer\Factory\ViewItemFactory;
use Greendot\EshopBundle\DataLayer\Factory\ViewItemListFactory;
use Greendot\EshopBundle\Service\DataLayer\DataLayerManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class DataLayerEventListener
{
    public function __construct(
        private readonly DataLayerManager $dataLayerManager,
        private readonly ViewItemListFactory $viewItemListFactory,
        private readonly ViewItemFactory $viewItemFactory,
        private readonly PurchaseFactory $purchaseFactory,
        private readonly CartFactory $cartFactory
    ){}

    #[AsEventListener(event: ViewItemListEvent::class)]
    public function onViewItemList(ViewItemListEvent $viewItemList): void
    {
        $eventData = $this->viewItemListFactory->create($viewItemList->getCategory(), $viewItemList->getProductFetchUri(), $viewItemList->getProductIds());
        $this->dataLayerManager->push(['event' => 'view_item_list', 'ecommerce' => $eventData], true);
    }

    #[AsEventListener(event: ViewItemEvent::class)]
    public function onViewItem(ViewItemEvent $viewItemList): void
    {
        $eventData = $this->viewItemFactory->create($viewItemList->getProduct(), $viewItemList->getSelectedVariants());
        $this->dataLayerManager->push(['event' => 'view_item', 'ecommerce' => $eventData], true);
    }

    #[AsEventListener(event: PurchaseEvent::class)]
    public function onPurchase(PurchaseEvent $purchaseEvent): void
    {
        $eventData = $this->purchaseFactory->create($purchaseEvent->getPurchase());
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

}