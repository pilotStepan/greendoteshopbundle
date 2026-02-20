<?php

namespace Greendot\EshopBundle\DataLayer\Factory;


use Greendot\EshopBundle\DataLayer\Data\Purchase\Purchase;
use Greendot\EshopBundle\DataLayer\Data\Purchase\PurchaseItem;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;

class PurchaseFactory
{
    use FactoryUtilsTrait;

    public function __construct(
        private readonly CurrencyManager $currencyManager,
        private readonly PurchaseRepository $purchaseRepository,
        private readonly PurchasePriceFactory $purchasePriceFactory
    ){}

    public function create(\Greendot\EshopBundle\Entity\Project\Purchase $purchase): Purchase
    {
        $currency = $this->currencyManager->get();

        $items = [];
        foreach ($purchase->getProductVariants() as $purchaseProductVariant){
            $items[] = $this->createPurchaseItem($purchaseProductVariant);
        }
        $purchasePrice = $this->purchasePriceFactory->create($purchase, $currency, VatCalculationType::WithVAT);
        $value = $purchasePrice->getPrice(true);
        $shipping = $purchasePrice->getTransportationPrice() + $purchasePrice->getPaymentPrice();

        $purchasePrice->setVatCalculationType(VatCalculationType::OnlyVAT);
        $tax = $purchasePrice->getPrice();

        return new Purchase(
            transaction_id: $purchase->getId(),
            value: $value,
            tax: $tax,
            shipping: $shipping ?? 0,
            currency: $currency?->getName() ?? 'CZK',
            customer_type: $this->getCustomerType($purchase),
            items: $items
        );
    }

    private function createPurchaseItem(PurchaseProductVariant $purchaseProductVariant): PurchaseItem
    {
        $category = $purchaseProductVariant?->getProductVariant()?->getProduct()?->getCategoryProducts()?->first()?->getCategory();
        $categories = [];
        if ($category){
            $categories[] = $this->getCategoryNameTreeUp($category);
        }

        $calculatedPrices = $purchaseProductVariant->getCalculatedPrices() ?? [];

        return new PurchaseItem(
            item_id: $purchaseProductVariant->getProductVariant()->getId(),
            item_name: $purchaseProductVariant->getProductVariant()->getProduct()->getName(),
            priceVat: $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceVat'),
            priceNoVat: $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceNoVat'),
            quantity: $purchaseProductVariant->getAmount(),
            categories: $categories
        );
    }

    protected function getCustomerType(\Greendot\EshopBundle\Entity\Project\Purchase $purchase): string
    {
        $client = $purchase?->getClient();
        if ($client && $client->isVerified() && !$client->isIsAnonymous()){
            $lastPurchase = $this->purchaseRepository->createQueryBuilder('p')
                ->setMaxResults(1)
                ->orderBy('p.date_issue', "DESC")
                ->andWhere("p.state NOT IN (:excludedStates)")
                ->setParameter('excludedStates', ['inquiry', 'draft', 'wishlist'])
                ->andWhere("p.client = :client")->setParameter("client", $client)
                ->andWhere('p.id != :currentPurchase')
                ->setParameter('currentPurchase', $purchase->getId())
                ->getQuery()->getOneOrNullResult();

            $lastValidDate = new \DateTime("now");
            $lastValidDate->modify("- 540 days");
            if ($lastPurchase && $lastPurchase->getDateIssue() && $lastPurchase->getDateIssue() > $lastValidDate) return 'returning';
        }

        return 'new';
    }

}