<?php

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Greendot\EshopBundle\Sms\ManageSms;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionSms;

/**
 * Listen to all notification-related events and sends sms
 */
final readonly class SmsSubscriber implements EventSubscriberInterface
{
    use NotificationGuardTrait;

    public function __construct(
        /** @var array<string,bool> */
        private array               $notificationMap, // config/packages/notifications.yaml:sms_notifications
        private ManageSms           $manageSms,
        private MessageBusInterface $bus,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // workflow events
            'workflow.purchase_flow.completed' => 'onPurchaseTransition',
        ];
    }

    public function onPurchaseTransition(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->bus->dispatch(new PurchaseTransitionSms($purchase->getId(), $transition));
    }
}