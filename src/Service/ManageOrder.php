<?php


namespace Greendot\EshopBundle\Service;


use Greendot\EshopBundle\Entity\Project\Currency;
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
use Twig\Extension\AbstractExtension;
class ManageOrder extends AbstractExtension
{
    private EntityManagerInterface $entityManager;
    private PurchaseRepository $orderRepository;
    private PaymentTypeRepository $paymentRepository;
    private TransportationRepository $transportationRepository;
    private Registry $workflow;
    private PriceCalculator $priceCalculator;
    private Currency $selectedCurrency;
    private PriceRepository $priceRepository;
    private CurrencyRepository $currencyRepository;
    private ClientRepository $clientRepository;


    public function __construct(
        Registry                 $workflowRegistry,
        EntityManagerInterface   $entityManager,
        PurchaseRepository       $orderRepository,
        PaymentTypeRepository    $paymentRepository,
        Registry                 $workflow,
        PriceCalculator          $priceCalculator,
        PriceRepository          $priceRepository,
        CurrencyRepository       $currencyRepository,
        TransportationRepository $transportationRepository,
        RequestStack             $requestStack,
        ClientRepository         $clientRepository,
        private NoteRepository   $noteRepository
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


}