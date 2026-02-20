<?php

namespace Greendot\EshopBundle\Twig;

use Greendot\EshopBundle\DataLayer\Event\PageViewEvent;
use Greendot\EshopBundle\DataLayer\Event\PurchaseEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemEvent;
use Greendot\EshopBundle\DataLayer\Event\ViewItemListEvent;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\DataLayer\DataLayerManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Attribute\AsTwigFunction;

class GoogleTagManagerExtension
{
    public function __construct(
        private readonly DataLayerManager $dataLayerManager,
        private readonly EventDispatcherInterface $eventDispatcher
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

    #[AsTwigFunction('gtm_view_item')]
    public function viewItem(Product $product, ?array $selectedVariants = null): void
    {
        $this->eventDispatcher->dispatch(new ViewItemEvent($product, $selectedVariants));
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

}