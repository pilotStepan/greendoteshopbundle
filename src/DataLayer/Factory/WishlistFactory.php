<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\Wishlist\AddToWishlist;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Service\CurrencyManager;

class WishlistFactory
{
    private readonly Currency $currency;

    public function __construct(
        private readonly CurrencyManager      $currencyManager,
        private readonly DataLayerItemFactory $dataLayerItemFactory,
    ) {
        $this->currency = $this->currencyManager->get();
    }

    public function create(PurchaseProductVariant $purchaseProductVariant, ?int $quantity = null): AddToWishlist
    {
        $item = $this->dataLayerItemFactory->createFromVariant(
            variant: $purchaseProductVariant->getProductVariant(),
            currency: $this->currency,
            quantity: $quantity ?? $purchaseProductVariant->getAmount(),
        );

        return new AddToWishlist(
            currency: $this->currency->getName() ?? 'CZK',
            value: $item->priceVat,
            items: [$item],
        );
    }
}