<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\Cart\ViewCart;
use Greendot\EshopBundle\DataLayer\Data\Checkout\AddPaymentInfo;
use Greendot\EshopBundle\DataLayer\Data\Checkout\AddShippingInfo;
use Greendot\EshopBundle\DataLayer\Data\Checkout\BeginCheckout;
use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;

class CheckoutFunnelFactory
{
    public function __construct(
        private readonly CurrencyManager      $currencyManager,
        private readonly DataLayerItemFactory $dataLayerItemFactory,
        private readonly PurchasePriceFactory $purchasePriceFactory,
    ) {}

    public function createViewCart(Purchase $purchase): ViewCart
    {
        $currency = $this->currencyManager->get();
        return new ViewCart(
            currency: $currency->getName() ?? 'CZK',
            value: $this->getValue($purchase, $currency),
            items: $this->buildItems($purchase, $currency),
        );
    }
    public function createBeginCheckout(Purchase $purchase): BeginCheckout
    {
        $currency = $this->currencyManager->get();
        return new BeginCheckout(
            currency: $currency->getName() ?? 'CZK',
            value: $this->getValue($purchase, $currency),
            items: $this->buildItems($purchase, $currency),
        );
    }

    public function createAddPaymentInfo(Purchase $purchase): AddPaymentInfo
    {
        $currency = $this->currencyManager->get();
        return new AddPaymentInfo(
            currency: $currency->getName() ?? 'CZK',
            value: $this->getValue($purchase, $currency),
            items: $this->buildItems($purchase, $currency),
        );
    }

    public function createAddShippingInfo(Purchase $purchase): AddShippingInfo
    {
        $currency = $this->currencyManager->get();
        return new AddShippingInfo(
            currency: $currency->getName() ?? 'CZK',
            value: $this->getValue($purchase, $currency),
            items: $this->buildItems($purchase, $currency),
        );
    }

    /** @return DataLayerItem[] */
    private function buildItems(Purchase $purchase, Currency $currency): array
    {
        $items = [];
        foreach ($purchase->getProductVariants() as $ppv) {
            $items[] = $this->dataLayerItemFactory->createFromVariant(
                variant: $ppv->getProductVariant(),
                currency: $currency,
                quantity: $ppv->getAmount(),
            );
        }
        return $items;
    }

    private function getValue(Purchase $purchase, Currency $currency): float
    {
        return $this->purchasePriceFactory->create($purchase, $currency, VatCalculationType::WithVAT)->getPrice(true) ?? 0.0;
    }
}