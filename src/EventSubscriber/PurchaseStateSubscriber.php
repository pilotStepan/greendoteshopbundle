<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Exception;
use LogicException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Event\Event;
use Greendot\EshopBundle\Service\DateService;
use Greendot\EshopBundle\Service\ManageVoucher;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Service\ManagePurchase;
use Symfony\Component\Workflow\Event\GuardEvent;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\AffiliateService;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class PurchaseStateSubscriber implements EventSubscriberInterface
{
    public function __construct
    (
        private EntityManagerInterface $entityManager,
        private ManageVoucher          $manageVoucher,
        private ManagePurchase         $managePurchase,
        private ManageClientDiscount   $manageClientDiscount,
        private AffiliateService       $affiliateService,
        private DateService            $dateService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.purchase_flow.guard.receive' => ['onGuardReceive'],
            'workflow.purchase_flow.transition.receive' => ['onReceive'],
            'workflow.purchase_flow.transition.payment' => ['onPayment'],
            'workflow.purchase_flow.transition.payment_issue' => ['onPaymentIssue'],
            'workflow.purchase_flow.transition.cancellation' => ['onCancellation'],
            'workflow.purchase_flow.transition.prepare_for_pickup' => ['onPrepareForPickup'],
            'workflow.purchase_flow.transition.send' => ['onSend'],
            'workflow.purchase_flow.transition.pick_up' => ['onPickUp'],
        ];
    }

    public function onGuardReceive(GuardEvent $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        if ($purchase->getProductVariants()->isEmpty()) {
            $event->setBlocked(true, 'Nelze vytvořit prázdnou objednávku');
            return;
        }

        foreach ($purchase->getProductVariants() as $ppv) {
            // pv doesn't have an availability set => it's purchasable
            if ($ppv->getProductVariant()->getAvailability()?->getIsPurchasable() === false) {
                $event->setBlocked(true, 'V košíku jsou nedostupné položky, než budete pokračovat, prosím je odeberte');
                return;
            }
        }

        if (!$purchase->getClient()) {
            $event->setBlocked(true, 'Objednávka musí mít přiřazeného klienta');
            return;
        }

        $paymentType = $purchase->getPaymentType();
        if (!$paymentType) {
            $event->setBlocked(true, "Nebyl vybrán typ platby");
            return;
        }

        $transportation = $purchase->getTransportation();
        if (!$transportation) {
            $event->setBlocked(true, "Nebyla vybrána doprava");
            return;
        }

        if (!$paymentType->getTransportations()->contains($transportation)) {
            $event->setBlocked(true, "Nekompatibilní typ platby a dopravy");
            return;
        }

        $missingConsent = $this->entityManager->getRepository(Consent::class)->findMissingRequiredConsent($purchase->getConsents());

        if ($missingConsent) {
            $event->setBlocked(true, "Povinný souhlas nebyl zaškrtnut: " . $missingConsent->getDescription());
            return;
        }

        try {
            $this->manageVoucher->validateVouchersTransition($purchase->getVouchersUsed(), 'use');
        } catch (LogicException $e) {
            $event->setBlocked(true, $e->getMessage());
            return;
        }

        $discount = $purchase->getClientDiscount();
        if ($discount) {
            try {
                $this->manageClientDiscount->guardUse($discount, $purchase);
            } catch (LogicException $e) {
                $event->setBlocked(true, $e->getMessage());
            }
        }

        try {
            $this->managePurchase->processVatNumber($purchase);
        } catch (Exception $e) {
            $event->setBlocked(true, "Chyba při ověřování DIČ: " . $e->getMessage());
            return;
        }
    }

    public function onReceive(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        foreach ($purchase->getVouchersUsed() as $voucher) {
            $this->manageVoucher->use($voucher, $purchase);
        }

        $clientDiscount = $purchase->getClientDiscount();
        if ($clientDiscount !== null) {
            $this->manageClientDiscount->use($clientDiscount, $purchase);
        }

        $this->manageVoucher->initiateVouchers($purchase);
        $this->managePurchase->generateTransportData($purchase);
        $this->dateService->calculatePurchaseDeliveryDate($purchase);
    }

    public function onPayment(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment');
        $this->affiliateService->dispatchCreateAffiliateEntryMessage($purchase);

        //? We don't want automatic invoice issue on payment
        // $this->managePurchase->issueInvoice($purchase); 
    }

    public function onPaymentIssue(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment_issue');
        $this->affiliateService->dispatchCancelAffiliateEntryMessage($purchase);
    }

    public function onCancellation(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment_issue');
        $this->affiliateService->dispatchCancelAffiliateEntryMessage($purchase);
    }

    public function onPrepareForPickup(Event $event): void
    {
        /** @var Purchase $purchase */
        // $purchase = $event->getSubject();
    }

    public function onSend(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->affiliateService->dispatchCreateAffiliateEntryMessage($purchase);
    }

    public function onPickUp(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->affiliateService->dispatchCreateAffiliateEntryMessage($purchase);
    }
}
