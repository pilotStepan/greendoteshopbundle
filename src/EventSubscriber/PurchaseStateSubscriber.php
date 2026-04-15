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
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\DataLayer\Event\PurchaseEvent;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

readonly class PurchaseStateSubscriber implements EventSubscriberInterface
{
    public function __construct
    (
        private EntityManagerInterface   $entityManager,
        private ManageVoucher            $manageVoucher,
        private ManagePurchase           $managePurchase,
        private ManageClientDiscount     $manageClientDiscount,
        private DateService              $dateService,
        private EventDispatcherInterface $eventDispatcher,
        #[Target('purchase_flow')]
        private WorkflowInterface        $purchaseWorkflow,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PWC::eventName('guard', PWC::T_CHECKOUT) => 'onGuardReceive',

            PWC::eventName('transition', PWC::T_CHECKOUT) => 'onReceive',
            PWC::eventName('transition', PWC::T_PAY_PAY) => 'onPayment',
            PWC::eventName('transition', PWC::T_PAY_RETRY) => 'onPayment',
            PWC::eventName('transition', PWC::T_PAY_FAIL) => 'onPaymentIssue',
            PWC::eventName('transition', PWC::T_CANCEL) => 'onCancellation',
            PWC::eventName('transition', PWC::T_PAY_REFUND) => 'onRefund',

            // Terminal cleanup
            PWC::eventName('entered', PWC::S_CANCELLED) => 'onEnterCancelled',
            PWC::eventName('entered', PWC::S_COMPLETED) => 'onEnterCompleted',

            // Funnels
            PWC::eventName('completed', PWC::T_LOG_DELIVER) => 'onTrackFinished',
            PWC::eventName('completed', PWC::T_LOG_PICKUP_DONE) => 'onTrackFinished',
            PWC::eventName('completed', PWC::T_PAY_PAY) => 'onTrackFinished',
            PWC::eventName('completed', PWC::T_PAY_RETRY) => 'onTrackFinished',
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


        try {
            $this->eventDispatcher->dispatch(new PurchaseEvent($purchase));
        } catch (Exception $exception) {
            //maybe add logg
        }

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

        $purchase->setWorkflowFlag(PWC::F_IS_PAID->value, true);

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment');

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
    }

    public function onRefund(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $purchase->setWorkflowFlag(PWC::F_IS_PAID->value, false);

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment_issue');
    }

    public function onCancellation(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment_issue');
    }

    public function onEnterCancelled(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $purchase->setMarking([PWC::S_CANCELLED->value => 1]);
    }

    public function onEnterCompleted(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);
    }

    public function onTrackFinished(CompletedEvent $event): void
    {
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->tryAutoComplete($purchase);
        $this->entityManager->flush();
    }

    private function tryAutoComplete(Purchase $purchase): void
    {
        if ($this->purchaseWorkflow->can($purchase, PWC::T_COMPLETE->value)) {
            $this->purchaseWorkflow->apply($purchase, PWC::T_COMPLETE->value);
        }
    }
}
