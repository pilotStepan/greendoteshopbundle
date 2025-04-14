<?php


namespace Greendot\EshopBundle\Service;


use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\NoteRepository;
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
        private NoteRepository                 $noteRepository,
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

    public function calculateInquiryNumber(Purchase|int $purchase)
    {
        if ($purchase instanceof Purchase) {
            return $purchase->getDateIssue()->getTimestamp() . $purchase->getId();
        } else {
            if (strlen((string)$purchase) > 10) {
                $purchaseId = substr((string)$purchase, 10);
                return $this->purchaseRepository->find($purchaseId);
            } else {
                throw new \InvalidArgumentException("Inquiry ID has a wrong format.", 500);
            }
        }
    }


    public function getNotifyEmail(Purchase $purchase): ?string
    {
        $note = $this->noteRepository->findOneBy(["type" => "E-mail pro notifikace", 'purchase' => $purchase]);
        //returns null or text of note in this case email
        return $note?->getText();
    }

    public function getRangeOfCalendarWeek(int $calendarWeek)
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
        // Pokud objednávka musí být vyzvedena osobně nebo na pobočce, tak return
        if (in_array($purchase->getTransportation()->getAction()->getId(), [1, 2])) return;

        $transportationId = $purchase->getTransportation()->getId();
        $parcelId = $this->parcelServiceProvider->get($transportationId)->createParcel($purchase);

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

    public function generateInvoice(Purchase $purchase): string
    {
        $invoiceNumber = $this->purchaseRepository->getNextInvoiceNumber();
        $purchase->setInvoiceNumber($invoiceNumber);
        return $this->invoiceMaker->createInvoiceOrProforma($purchase);
    }
}