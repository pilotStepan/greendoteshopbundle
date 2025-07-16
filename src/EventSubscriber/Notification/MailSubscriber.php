<?php

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Greendot\EshopBundle\Event\PasswordResetRequestedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionEmail;

/**
 * Listen to all notification-related events and sends mails
 */
final readonly class MailSubscriber implements EventSubscriberInterface
{
    use NotificationGuardTrait;

    public function __construct(
        /** @var array<string,bool> */
        private array               $notificationMap, // config/packages/notifications.yaml:email_notifications
        private ManageMails         $manageMails,
        private MessageBusInterface $bus,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // workflow events
            'workflow.purchase_flow.completed' => 'onPurchaseTransition',

            // custom events
            PasswordResetRequestedEvent::class => 'onPasswordReset',
        ];
    }

    public function onPurchaseTransition(CompletedEvent $event): void
    {
        if (!$this->shouldNotify($event, $this->notificationMap)) return;

        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->bus->dispatch(new PurchaseTransitionEmail($purchase->getId(), $transition));
    }

    public function onPasswordReset(PasswordResetRequestedEvent $event): void
    {
        $this->manageMails->sendPasswordResetEmail($event->recipient, $event->token);
    }
}
