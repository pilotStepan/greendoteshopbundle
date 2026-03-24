<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Purchase;

use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Event\Event;
use Greendot\EshopBundle\Service\DateService;
use Greendot\EshopBundle\Service\ManageVoucher;
use Symfony\Component\Workflow\Event\GuardEvent;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\AffiliateService;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\DataLayer\Event\PurchaseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionSms;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionEmail;

#[AutoconfigureTag('kernel.event_subscriber')]
readonly class DefaultPurchaseWorkflowEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface   $entityManager,
        private ManageVoucher            $manageVoucher,
        private ManagePurchase           $managePurchase,
        private ManageClientDiscount     $manageClientDiscount,
        private AffiliateService         $affiliateService,
        private DateService              $dateService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface          $logger,
        private MessageBusInterface      $bus,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.purchase_flow.guard.checkout' => 'onGuardCheckout',
            'workflow.purchase_flow.transition.checkout' => 'onCheckout',
            'workflow.purchase_flow.transition.capture_payment' => 'onCapturePayment',
            'workflow.purchase_flow.transition.mark_payment_failed' => 'onMarkPaymentFailed',
            'workflow.purchase_flow.transition.cancel' => 'onCancel',
            'workflow.purchase_flow.transition.complete' => 'onComplete',

            // triggered when purchase has completed ANY transition
            'workflow.purchase_flow.completed' => 'dispatchNotifications',
        ];
    }

    public function onGuardCheckout(GuardEvent $event): void
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
            $this->manageVoucher->guardVouchersTransition($purchase->getVouchersUsed(), 'use');
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

    public function onCheckout(Event $event): void
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

        try {
            $this->eventDispatcher->dispatch(new PurchaseEvent($purchase));
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    public function onCapturePayment(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->manageVoucher->callVouchersTransition(
            $purchase->getVouchersIssued(),
            'payment',
        );
        $this->affiliateService->dispatchCreateAffiliateEntryMessage($purchase);
    }

    public function onMarkPaymentFailed(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->manageVoucher->callVouchersTransition(
            $purchase->getVouchersIssued(),
            'payment_issue',
        );
        $this->affiliateService->dispatchCancelAffiliateEntryMessage($purchase);
    }

    public function onCancel(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->manageVoucher->callVouchersTransition(
            $purchase->getVouchersIssued(),
            'payment_issue',
        );
        $this->affiliateService->dispatchCancelAffiliateEntryMessage($purchase);
    }

    public function onComplete(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->affiliateService->dispatchCreateAffiliateEntryMessage($purchase);
    }

    public function dispatchNotifications(CompletedEvent $event): void
    {
        // silenced from CMS
        if ($event->getContext()['silent'] ?? false) {
            return;
        }

        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        $transitionName = $event->getTransition()->getName();

        $notificationMap = [
            'email_notification' => PurchaseTransitionEmail::class,
            'sms_notification'   => PurchaseTransitionSms::class,
        ];

        foreach ($notificationMap as $metadataKey => $messageClass) {
            // dispatch if workflow.yaml allows
            if ($event->getMetadata($metadataKey, $transitionName) === true) {
                $this->bus->dispatch(new $messageClass($purchase->getId(), $transitionName));
            }
        }
    }
}