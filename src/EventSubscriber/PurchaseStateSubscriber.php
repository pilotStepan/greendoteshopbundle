<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\ManageVoucher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Registry as WorkflowRegistry;


readonly class PurchaseStateSubscriber implements EventSubscriberInterface
{
    public function __construct
    (
        private ManageMails            $manageMails,
        private EntityManagerInterface $entityManager,
        private ManageVoucher          $manageVoucher,
        private ManagePurchase         $managePurchase,
        private WorkflowRegistry       $workflowRegistry,
        private ManageClientDiscount   $manageClientDiscount,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.purchase_flow.guard.create' => ['onGuardCreate'],
            'workflow.purchase_flow.guard.receive' => ['onGuardReceive'],
            'workflow.purchase_flow.transition.create' => ['onCreate'],
            'workflow.purchase_flow.transition.receive' => ['onReceive'],
            'workflow.purchase_flow.transition.payment' => ['onPayment'],
            'workflow.purchase_flow.transition.payment_issue' => ['onPaymentIssue'],
            'workflow.purchase_flow.transition.cancellation' => ['onCancellation'],
            'workflow.purchase_flow.transition.prepare_for_pickup' => ['onPrepareForPickup'],
            'workflow.purchase_flow.transition.send' => ['onSend'],
            'workflow.purchase_flow.transition.pick_up' => ['onPickUp'],
            'workflow.purchase_flow.completed.create' => ['onCompletedCreate'],
        ];
    }

    public function onGuardCreate(GuardEvent $event)
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        if ($purchase->getProductVariants()->isEmpty()) {
            $event->setBlocked(true, 'Cannot create an empty purchase.');
            return null;
        }

        // check vouchers
        $vouchers = $purchase->getVouchersUsed();
        if (!$vouchers->isEmpty()) {
            foreach ($vouchers as $v) {
                /*
                 * TODO nonexistent attribute
                 */
                if (!$this->voucherFlowWorkflow->can($v, "use")) {
                    $event->setBlocked(true, "Purchase has invalid voucher ID:" . $v->getId());
                    return null;
                }
            }
        }

        // check discount
        $discount = $purchase->getClientDiscount();
        if ($discount !== null && !$this->manageClientDiscount->isAvailable($discount, $purchase)) {
            $event->setBlocked(true, "Purchase has invalid clientDiscount");
            return null;
        }

        // check payment and transportation
        $paymentType = $purchase->getPaymentType();
        if ($paymentType === null) {
            $event->setBlocked(true, "Purchase paymentType is null");
            return null;
        }
        if ($purchase->getTransportation() === null) {
            $event->setBlocked(true, "Purchase transportation is null");
            return null;
        }
        if (!$this->managePurchase->isPaymentAvailable($paymentType, $purchase)) {
            $event->setBlocked(true, "Purchase paymentType and transportation are not compatible");
            return null;
        }
    }

    public function onGuardReceive(GuardEvent $event)
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        // check vouchers
        $vouchers = $purchase->getVouchersUsed();
        if (!$vouchers->isEmpty()) {
            foreach ($vouchers as $v) {
                /*
                 * TODO nonexistent attribute
                 */
                if ($this->voucherFlowWorkflow->can($v, "use")) {
                    $event->setBlocked(true, "Purchase has invalid voucher ID:" . $v->getId());
                    return null;
                }
            }
        }

        // check discount
        $discount = $purchase->getClientDiscount();
        if ($discount !== null && !$this->manageClientDiscount->isAvailable($discount, $purchase)) {
            $event->setBlocked(true, "Purchase has invalid clientDiscount");
            return null;
        }

        // check payment and transportation
        $paymentType = $purchase->getPaymentType();
        if ($paymentType === null) {
            $event->setBlocked(true, "Purchase paymentType is null");
            return null;
        }
        if ($purchase->getTransportation() === null) {
            $event->setBlocked(true, "Purchase transportation is null");
            return null;
        }
        if (!$this->managePurchase->isPaymentAvailable($paymentType, $purchase)) {
            $event->setBlocked(true, "Purchase paymentType and transportation are not compatible");
            return null;
        }
    }

    public function onCreate(TransitionEvent $event)
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $purchase->setState('new');
    }

    public function onReceive(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->manageVoucher->handleUsedVouchers($purchase, 'used');
        $this->manageClientDiscount->use($purchase->getClientDiscount(), $purchase);
        $this->managePurchase->generateTransportData($purchase);
        $this->manageVoucher->initiateVouchers($purchase);

        $this->manageMails->sendOrderReceiveEmail($purchase, 'mail/specific/order-receive.html.twig');
    }

    public function onPayment(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        foreach ($purchase->getVouchersIssued() as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if ($workflow->can($voucher, 'paid')) {
                $workflow->apply($voucher, 'paid');
            }
        }

        $invoicePath = $this->managePurchase->generateInvoice($purchase);
        $this->manageMails->sendPaymentReceivedEmail($purchase, $invoicePath, 'mail/specific/payment-received.html.twig');

        $this->entityManager->flush();
    }


    public function onPaymentIssue(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        foreach ($purchase->getVouchersIssued() as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if ($workflow->can($voucher, 'not_paid')) {
                $workflow->apply($voucher, 'not_paid');
            }
        }

        $this->manageMails->sendEmail($purchase, 'mail/specific/payment-not-received.html.twig');
    }

    public function onCancellation(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        foreach ($purchase->getVouchersIssued() as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if ($workflow->can($voucher, 'not_paid')) {
                $workflow->apply($voucher, 'not_paid');
            }
        }

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
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            throw new \LogicException('Expected subject of type Purchase, got ' . get_class($purchase));
        }

        $this->manageMails->sendEmail($purchase, 'mail/specific/order-picked-up.html.twig');
    }

    public function onCompletedCreate(CompletedEvent $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->entityManager->persist($purchase);
        $this->entityManager->flush();
    }
}
