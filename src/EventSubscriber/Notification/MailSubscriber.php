<?php

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Event\PasswordResetRequestedEvent;
use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Service\ManagePurchase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Listen to all notification-related events and sends mails
 */
final readonly class MailSubscriber implements EventSubscriberInterface
{
    use NotificationGuardTrait;

    public function __construct(
        /** @var array<string,bool> */
        private array          $notificationMap, // config/packages/notifications.yaml:email_notifications
        private ManageMails    $manageMails,
        private ManagePurchase $managePurchase,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // purchase workflow
            // We use *completed.* so the state machine has already committed its change
            'workflow.purchase_flow.completed.receive' => 'onCompletedReceive',
            'workflow.purchase_flow.completed.payment' => 'onCompletedPayment',
            'workflow.purchase_flow.completed.payment_issue' => 'onCompletedPaymentIssue',
            'workflow.purchase_flow.completed.cancellation' => 'onCompletedCancellation',
            'workflow.purchase_flow.completed.prepare_for_pickup' => 'onCompletedPrepareForPickup',
            'workflow.purchase_flow.completed.send' => 'onCompletedSend',
            'workflow.purchase_flow.completed.pick_up' => 'onCompletedPickUp',
            PasswordResetRequestedEvent::class => 'onPasswordReset',
        ];
    }

    public function onCompletedPayment(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
//        $invoicePath = $this->managePurchase->generateInvoice($purchase);
//        $this->manageMails->sendPaymentReceivedEmail(
//            $purchase,
//            $invoicePath,
//            'mail/specific/payment-received.html.twig'
//        );
    }

    public function onCompletedReceive(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
//        $this->manageMails->sendOrderReceiveEmail(
//            $purchase,
//            'mail/specific/order-receive.html.twig'
//        );
    }

    public function onCompletedPaymentIssue(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
//        $this->manageMails->sendEmail(
//            $purchase,
//            'mail/specific/payment-not-received.html.twig'
//        );
    }

    public function onCompletedCancellation(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
//        $this->manageMails->sendEmail(
//            $purchase,
//            'mail/specific/order-canceled.html.twig'
//        );
    }

    public function onCompletedPrepareForPickup(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
//        $this->manageMails->sendEmail(
//            $purchase,
//            'mail/specific/order-ready-for-pickup.html.twig'
//        );
    }

    public function onCompletedSend(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
//        $this->manageMails->sendEmail(
//            $purchase,
//            'mail/specific/order-shipped.html.twig'
//        );
    }

    public function onCompletedPickUp(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
//        $this->manageMails->sendEmail(
//            $purchase,
//            'mail/specific/order-picked-up.html.twig'
//        );
    }

    public function onPasswordReset(PasswordResetRequestedEvent $event): void
    {
        $this->manageMails->sendPasswordResetEmail($event->recipient, $event->token);
    }
}
