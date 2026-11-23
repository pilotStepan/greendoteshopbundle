<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\Cart\CartModified;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Service\CurrencyManager;

class CartFactory
{
    private readonly Currency $currency;
    public function __construct(
        private readonly CurrencyManager            $currencyManager,
        private readonly DataLayerItemFactory       $dataLayerItemFactory,
    )
    {
        $this->currency = $this->currencyManager->get();
    }

    public function create(PurchaseProductVariant $purchaseProductVariant, int $quantity): CartModified
    {
        $item = $this->dataLayerItemFactory->createFromVariant(
            variant: $purchaseProductVariant->getProductVariant(),
            currency: $this->currency,
            quantity: $quantity > 0 ? $quantity : $purchaseProductVariant->getAmount()
        );
        return new CartModified(
            currency: $this->currency->getName() ?? 'CZK',
            value: round($item->priceVat * $item->quantity, 2),
            items: [$item]
        );
    }

}