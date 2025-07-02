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
        private array     $notificationMap, // config/packages/notifications.yaml:sms_notifications
        private ManageSms $manageSms,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // purchase workflow
            'workflow.purchase_flow.completed.receive' => 'onOrderStateChange',
            'workflow.purchase_flow.completed.payment' => 'onOrderStateChange',
            'workflow.purchase_flow.completed.prepare_for_pickup' => 'onOrderStateChange',
        ];
    }

    public function onOrderStateChange(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->manageSms->sendOrderStateSms($purchase);
    }
}