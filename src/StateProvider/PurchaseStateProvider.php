<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyResolver;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;

readonly class PurchaseStateProvider implements ProviderInterface
{
    public function __construct(
        private PurchaseRepository         $purchaseRepository,
        private CurrencyResolver           $currencyResolver,
        private PurchasePriceFactory       $purchasePriceFactory,
        private ProductVariantPriceFactory $productVariantPriceFactory,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Purchase
    {
        $purchase = $this->purchaseRepository->findOneBySession('purchase');
        if (!$purchase) return null;


        $currency = $this->currencyResolver->resolve();

        $purchasePriceCalc = $this->purchasePriceFactory->create(
            $purchase,
            $currency,
            VatCalculationType::WithVAT
        );

        $purchase->setTotalPrice(
            $purchasePriceCalc->getPrice(true)
        );
        $purchase->setTotalPriceNoServices(
            $purchasePriceCalc->getPrice(false)
        );

        if ($purchase->getTransportation()) {
            $purchase->setTransportationPrice(
                $purchasePriceCalc->getTransportationPrice()
            );
        }
        if ($purchase->getPaymentType()) {
            $purchase->setPaymentPrice(
                $purchasePriceCalc->getPaymentPrice()
            );
        }

        foreach ($purchase->getProductVariants() as $productVariant) {
            $productVariantPriceCalc = $this->productVariantPriceFactory->create(
                $productVariant,
                $currency,
                vatCalculationType: VatCalculationType::WithVAT,
            );
            $productVariant->setTotalPrice(
                $productVariantPriceCalc->getPrice()
            );
        }

        return $purchase;
    }
}