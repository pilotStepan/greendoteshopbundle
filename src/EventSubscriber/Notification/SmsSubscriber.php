<?php

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\ManageSms;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Listen to all notification-related events and sends sms
 */
final readonly class SmsSubscriber implements EventSubscriberInterface
{
    use NotificationGuardTrait;

    public function __construct(
        /** @var array<string,bool> */
        private array            $notificationMap, // config/packages/notifications.yaml:sms_notifications
        private ManageSms        $manageSms,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // purchase workflow
            'workflow.purchase_flow.completed.receive' => 'onCompletedReceive',
            'workflow.purchase_flow.completed.payment' => 'onCompletedPayment',
            'workflow.purchase_flow.completed.prepare_for_pickup' => 'onCompletedPrepareForPickup',
        ];
    }

    public function onCompletedReceive(CompletedEvent $event): void
    {
        $name = $this->workflowEventName($event);
        $context = $event->getContext();
        if (!$this->shouldNotify($name, $context, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->manageSms->sendOrderReceiveSms($purchase);
    }

    public function onCompletedPayment(CompletedEvent $event): void
    {
        $name = $this->workflowEventName($event);
        $context = $event->getContext();
        if (!$this->shouldNotify($name, $context, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->manageSms->sendPaymentReceivedSms($purchase);
    }

    public function onCompletedPrepareForPickup(CompletedEvent $event): void
    {
        $name = $this->workflowEventName($event);
        $context = $event->getContext();
        if (!$this->shouldNotify($name, $context, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->manageSms->sendPrepareForPickupSms($purchase);
    }
}