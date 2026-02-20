<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\Cart\CartItem;
use Greendot\EshopBundle\DataLayer\Data\Cart\CartModified;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;

class CartFactory
{
    private readonly Currency $currency;
    public function __construct(
        private readonly CurrencyManager $currencyManager,
        private readonly ProductVariantPriceFactory $productVariantPriceFactory
    )
    {
        $this->currency = $this->currencyManager->get();
    }

    public function create(PurchaseProductVariant $purchaseProductVariant, int $quantity): CartModified
    {
        $item = $this->createItem($purchaseProductVariant, $quantity);
        return new CartModified(
            currency: $this->currency->getName() ?? 'CZK',
            value: $item->price,
            items: [$item]
        );
    }


    public function createItem(PurchaseProductVariant $purchaseProductVariant, ?int $quantity = null): CartItem
    {
        $productVariantPrice = $this->productVariantPriceFactory->create($purchaseProductVariant->getProductVariant(), $this->currency, $quantity, VatCalculationType::WithVAT);

        return new CartItem(
            item_id: $purchaseProductVariant->getProductVariant()->getId(),
            item_name: $purchaseProductVariant->getProductVariant()->getProduct()->getName(),
            quantity: $quantity !== null ? $quantity : $purchaseProductVariant->getAmount(),
            price: $productVariantPrice->getPrice(),
            item_brand: $purchaseProductVariant?->getProductVariant()?->getProduct()?->getProducer()?->getName() ?? 'Unknown',
        );
    }

}