<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\ManageVoucher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;


readonly class PurchaseStateSubscriber implements EventSubscriberInterface
{
    public function __construct
    (
        private ManageMails            $manageMails,
        private EntityManagerInterface $entityManager,
        private ManageVoucher          $manageVoucher,
        private ManagePurchase         $managePurchase,
        private ManageClientDiscount   $manageClientDiscount,
    )
    {
    }

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

        $missingConsent = $this->entityManager
            ->getRepository('GreendotEshopBundle:Project\Consent')
            ->findMissingRequiredConsent($purchase->getConsents());
        if ($missingConsent) {
            $event->setBlocked(true, "Povinný souhlas nebyl zaškrtnut: " . $missingConsent->getDescription());
            return;
        }

        $invalidVoucher = $this->manageVoucher->validateUsedVouchers($purchase, 'use');
        if ($invalidVoucher) {
            $event->setBlocked(true, "Nelze uplatnit neplatný voucher: " . $invalidVoucher->getHash());
            return;
        }

        $discount = $purchase->getClientDiscount();
        if ($discount && !$this->manageClientDiscount->isAvailable($discount, $purchase)) {
            $event->setBlocked(true, "Objednávka má neplatnou klientskou slevu");
            return;
        }
    }

    public function onReceive(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->entityManager->wrapInTransaction(function() use ($purchase) {
            $this->manageVoucher->handleUsedVouchers($purchase, 'use');
            $this->manageClientDiscount->use($purchase->getClientDiscount(), $purchase);
            $this->managePurchase->generateTransportData($purchase);
            $this->manageVoucher->initiateVouchers($purchase);
        });

        $this->manageMails->sendOrderReceiveEmail($purchase, 'mail/specific/order-receive.html.twig');
    }

    public function onPayment(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->entityManager->wrapInTransaction(function() use ($purchase, &$invoicePath) {
            $this->manageVoucher->handleIssuedVouchers($purchase, 'payment');
            $invoicePath = $this->managePurchase->generateInvoice($purchase);
        });

        $this->manageMails->sendPaymentReceivedEmail($purchase, $invoicePath, 'mail/specific/payment-received.html.twig');
    }


    public function onPaymentIssue(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->entityManager->wrapInTransaction(function() use ($purchase) {
            $this->manageVoucher->handleIssuedVouchers($purchase, 'payment_issue');
        });

        $this->manageMails->sendEmail($purchase, 'mail/specific/payment-not-received.html.twig');
    }

    public function onCancellation(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->entityManager->wrapInTransaction(function() use ($purchase) {
            $this->manageVoucher->handleIssuedVouchers($purchase, 'payment_issue');
        });

        $this->manageMails->sendEmail($purchase, 'mail/specific/order-canceled.html.twig');
    }

    public function onPrepareForPickup(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        $this->manageMails->sendEmail($purchase, 'mail/specific/order-ready-for-pickup.html.twig');
    }

    public function onSend(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        $this->manageMails->sendEmail($purchase, 'mail/specific/order-shipped.html.twig');
    }

    public function onPickUp(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        $this->manageMails->sendEmail($purchase, 'mail/specific/order-picked-up.html.twig');
    }
}
