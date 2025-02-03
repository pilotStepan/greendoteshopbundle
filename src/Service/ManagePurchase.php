<?php


namespace Greendot\EshopBundle\Service;


use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\NoteRepository;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use http\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;
use Twig\Extension\AbstractExtension;
use Psr\Log\LoggerInterface;

class ManagePurchase extends AbstractExtension
{
    private EntityManagerInterface $entityManager;
    private PurchaseRepository $orderRepository;
    private PaymentTypeRepository $paymentRepository;
    private TransportationRepository $transportationRepository;
    private Workflow $workflow;
    private PriceCalculator $priceCalculator;
    private Currency $selectedCurrency;
    private PriceRepository $priceRepository;
    private CurrencyRepository $currencyRepository;
    private ClientRepository $clientRepository;
    private LoggerInterface $logger;
    private ManageClientDiscount $manageClientDiscount;

    public function __construct(
        Registry                 $workflowRegistry,
        EntityManagerInterface   $entityManager,
        PurchaseRepository       $orderRepository,
        PaymentTypeRepository    $paymentRepository,
        Workflow                 $workflow,
        PriceCalculator          $priceCalculator,
        PriceRepository          $priceRepository,
        CurrencyRepository       $currencyRepository,
        TransportationRepository $transportationRepository,
        RequestStack             $requestStack,
        ClientRepository         $clientRepository,
        private NoteRepository   $noteRepository,
        LoggerInterface          $logger,
        ManageClientDiscount     $manageClientDiscount,

    )
    {
        $this->entityManager = $entityManager;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->workflow = $workflow;
        $this->workflowRegistry = $workflowRegistry;
        $this->priceCalculator = $priceCalculator;
        $this->currencyRepository = $currencyRepository;
        $this->priceRepository = $priceRepository;
        $this->transportationRepository = $transportationRepository;
        $this->clientRepository = $clientRepository;
        $this->logger = $logger;


        //this has to be here, for some reason this ManageOrderService is being called before session is even established
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
                return $this->orderRepository->find($purchaseId);
            } else {
                throw new InvalidArgumentException("Inquiry ID has a wrong format.", 500);
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

    public function generateTransportData(Purchase $purchase, $purchasePrice): void
    {
        $transportationId = $purchase->getTransportation()->getId();
        $parcelId         = null;

        switch ($transportationId) {
            case 4:
                $parcelId = $this->czechPostParcel->createParcel($purchase, $purchasePrice);
                break;
            case 3:
                $parcelId = $this->packeteryParcel->createParcel($purchase, $purchasePrice);
                break;
        }

        if ($parcelId) {
            $purchase->setTransportNumber($parcelId);
        } else {
            $purchaseFlow = $this->workflow->get($purchase);
            if ($purchaseFlow->can($purchase, 'cancellation')) {
                $purchaseFlow->apply($purchase, 'cancellation');
                $this->logger->error('Failed to create parcel for purchase. Order cancelled.', ['purchaseId' => $purchase->getId()]);
            } else {
                $this->logger->error('Failed to create parcel for purchase and unable to cancel.', ['purchaseId' => $purchase->getId()]);
            }
        }
    }

    // checks if paymentType and purchase.transportation are linked, returns true if ok, false if not ok
    public function isPaymentAvailable(PaymentType $paymentType, Purchase $purchase) : bool
    {
        $transportation = $purchase->getTransportation();

        // check empty
        if ($transportation === null)
        {
            throw new  \Exception("Purchase has no transportation");
        }

        // check if ok
        if($paymentType->getTransportations()->contains($transportation))
        {
            return true;
        }
        return false;
    }

    // checks if all vouchers and discounts are available, returns true if ok, false if not ok
    public function checkVouchersAndDiscount(Purchase $purchase) : bool
    {

        // check vouchers
        $vouchers = $purchase->getVouchersUsed();
        if (!$vouchers->isEmpty())
        {
            foreach ($vouchers as $v){
                if (!$this->workflow->can($v, "use")) {
                    return false;
                }
            }
        }

        // check discount
        $discount = $purchase->getClientDiscount();
        if ($discount !== null && !$this->manageClientDiscount->isAvailable($discount, $purchase))
        {
            return false;
        }

        // all is ok
        return true;
    }



}