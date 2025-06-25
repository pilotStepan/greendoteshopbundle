<?php


namespace Greendot\EshopBundle\Service;


use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\MessageRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\Parcel\ParcelServiceProvider;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\Registry;
use Twig\Extension\AbstractExtension;
use Psr\Log\LoggerInterface;

class ManagePurchase extends AbstractExtension
{
    private Currency $selectedCurrency;

    public function __construct(
        private readonly Registry              $workflowRegistry,
        private readonly PurchaseRepository    $purchaseRepository,
        private readonly CurrencyRepository    $currencyRepository,
        private readonly MessageRepository     $messageRepository,
        private readonly LoggerInterface       $logger,
        private readonly InvoiceMaker          $invoiceMaker,
        private readonly ParcelServiceProvider $parcelServiceProvider,
        RequestStack                           $requestStack,
    )
    {
        // this has to be here, for some reason this ManageOrderService is being called before session is even established
        try {
            if ($requestStack->getSession()->isStarted() and $requestStack->getSession()->get('selectedCurrency')) {
                $this->selectedCurrency = $requestStack->getSession()->get('selectedCurrency');
            } else {
                $this->selectedCurrency = $this->currencyRepository->findOneBy(['isDefault' => true]);
            }
        } catch (SessionNotFoundException $exception) {
            $this->selectedCurrency = $this->currencyRepository->findOneBy(['isDefault' => true]);
        }
    }

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

    public function getNotifyEmail(Purchase $purchase): ?string
    {
        $note = $this->messageRepository->findOneBy(["type" => "E-mail pro notifikace", 'purchase' => $purchase]);
        //returns null or text of note in this case email
        return $note?->getText();
    }

    public function getRangeOfCalendarWeek(int $calendarWeek): array
    {
        $currentYear = date('Y');
        $currentMonth = date('n');

        $dto = new \DateTime();
        $dto->setISODate($currentYear, $calendarWeek);
        if ($dto->format('n') <= $currentMonth - 2) {
            // If the week is in the past 2 months, use the current year plus 1.
            $year = $currentYear + 1;
        } else {
            // Otherwise, use the current year.
            $year = $currentYear;
        }

        $dto->setISODate($year, $calendarWeek);

        $from = clone $dto;
        $to = clone $dto;

        $from->modify('this Monday');
        $to->modify('this Sunday');

        return [
            'from' => $from,
            'until' => $to
        ];
    }

    public function generateTransportData(Purchase $purchase): void
    {
        $transportationId = $purchase->getTransportation()->getId();
        $parcelService = $this->parcelServiceProvider->get($transportationId);
        if (!$parcelService) return;

        $parcelId = $parcelService->createParcel($purchase);
        if ($parcelId) {
            $purchase->setTransportNumber($parcelId);
            return;
        }

        $workflow = $this->workflowRegistry->get($purchase);
        if ($workflow->can($purchase, 'cancellation')) {
            $workflow->apply($purchase, 'cancellation');
            $this->logger->error('Failed to create parcel for purchase. Order cancelled.', ['purchaseId' => $purchase->getId()]);
        } else {
            $this->logger->error('Failed to create parcel for purchase and unable to cancel.', ['purchaseId' => $purchase->getId()]);
        }
    }

    public function generateInvoice(Purchase $purchase): ?string
    {
        $invoiceNumber = $this->purchaseRepository->getNextInvoiceNumber();
        $purchase->setInvoiceNumber($invoiceNumber);
        return $this->invoiceMaker->createInvoiceOrProforma($purchase);
    }

    // check if all products in purchase are available
    public function isPurchaseValid(Purchase $purchase): bool
    {
        $purchaseProductVariants = $purchase->getProductVariants();

        foreach ($purchaseProductVariants as $purchaseProductVariant){
            $productVariant = $purchaseProductVariant->getProductVariant();
            if ($productVariant->getAvailability()->getId() !== 1){
                return false;
            }
        }
        return true;
    }
}