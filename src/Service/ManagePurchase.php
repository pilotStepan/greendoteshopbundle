<?php


namespace Greendot\EshopBundle\Service;


use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\Parcel\ParcelServiceProvider;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Entity\Project\ProductVariant;

readonly class ManagePurchase
{
    public function __construct(
        private CurrencyResolver            $currencyResolver,
        private PurchasePriceFactory        $purchasePriceFactory,
        private ProductVariantPriceFactory  $productVariantPriceFactory,
        private PurchaseRepository          $purchaseRepository,
        private ParcelServiceProvider       $parcelServiceProvider,
    )
    { }

    public function addProductVariantToPurchase(Purchase $purchase, ProductVariant $productVariant, $amount = 1): Purchase
    {
        $purchaseProductVariants = $purchase->getProductVariants();
        $hasProductVariant = false;
        foreach ($purchaseProductVariants as $purchaseProductVariant) {
            $variant = $purchaseProductVariant->getProductVariant();
            if ($variant->getId() === $productVariant->getId()) {
                $hasProductVariant = true;
                $purchaseProductVariant->setAmount($purchaseProductVariant->getAmount() + $amount);
                break;
            }
        }

        if (!$hasProductVariant) {
            $purchaseProductVariant = new PurchaseProductVariant();
            $purchaseProductVariant->setProductVariant($productVariant);
            $purchaseProductVariant->setAmount($amount);
            $purchase->addProductVariant($purchaseProductVariant);
        }
        return $purchase;
    }

    public function generateInquiryNumber(Purchase $purchase): string
    {
        return sprintf('%010d%s', $purchase->getDateIssue()->getTimestamp(), $purchase->getId());
    }

    public function findPurchaseByInquiryNumber(string $inquiryNumber): Purchase
    {
        // The inquiry number is expected to be at least 11 characters (10 for timestamp, then the purchase ID)
        if (strlen($inquiryNumber) <= 10) {
            throw new \InvalidArgumentException("Inquiry ID has a wrong format.");
        }

        // Extract the purchase ID (after the first 10 characters)
        $purchaseId = substr($inquiryNumber, 10);
        $purchase = $this->purchaseRepository->find($purchaseId);
        if (!$purchase) {
            throw new \RuntimeException("Purchase not found for inquiry number: $inquiryNumber.");
        }
        return $purchase;
    }

    /* TODO: process parcel creating via messenger, handle failed parcel creation */
    public function generateTransportData(Purchase $purchase): void
    {
        $parcelService = $this->parcelServiceProvider->getByPurchase($purchase);
        $parcelId = $parcelService?->createParcel($purchase);
        $purchase->setTransportNumber($parcelId);
    }

    public function issueInvoice(Purchase $purchase): void
    {
        $invoiceNumber = $this->purchaseRepository->getNextInvoiceNumber();
        $purchase->setInvoiceNumber($invoiceNumber);
        $purchase->setDateInvoiced(new \DateTime());
    }

    public function isPurchaseValid(Purchase $purchase): bool
    {
        $purchaseProductVariants = $purchase->getProductVariants();

        foreach ($purchaseProductVariants as $purchaseProductVariant) {
            $productVariant = $purchaseProductVariant->getProductVariant();
            if ($productVariant->getAvailability()->getId() !== 1) {
                return false;
            }
        }
        return true;
    }

    // sets required price data for pased Purchase entity
    public function PreparePrices(Purchase $purchase) : Purchase
    {
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