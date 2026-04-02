<?php

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Greendot\EshopBundle\Sms\ManageSms;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Greendot\EshopBundle\Event\PurchaseWorkflowContract as PWC;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionSms;

/**
 * Listen to all notification-related events and sends sms
 */
final readonly class SmsSubscriber implements EventSubscriberInterface
{
    use NotificationGuardTrait;

    public function __construct(
        /**
         * @var array<string,bool>
         * @deprecated fallback for config/packages/notifications.yaml:sms_notifications
         */
        private array               $notificationMap,
        private ManageSms           $manageSms,
        private MessageBusInterface $bus,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // workflow events
            PWC::eventName('completed') => 'onPurchaseTransition',
        ];
    }

    public function onPurchaseTransition(CompletedEvent $event): void
    {
        if (!$this->shouldNotifyChannel($event, $this->notificationMap, 'sms')) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->bus->dispatch(new PurchaseTransitionSms($purchase->getId(), $transition));
    }
}