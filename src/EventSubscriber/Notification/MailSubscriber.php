<?php

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionEmail;

/**
 * Listen to all notification-related events and sends mails
 */
final readonly class MailSubscriber implements EventSubscriberInterface
{
    use NotificationGuardTrait;

    public function __construct(
        /**
         * @var array<string,bool>
         * @deprecated fallback for config/packages/notifications.yaml:email_notifications
         */
        private array               $notificationMap,
        private ManageMails         $manageMails,
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
        if (!$this->shouldNotifyChannel($event, $this->notificationMap, 'mail')) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->bus->dispatch(new PurchaseTransitionEmail($purchase->getId(), $transition));
    }
}
