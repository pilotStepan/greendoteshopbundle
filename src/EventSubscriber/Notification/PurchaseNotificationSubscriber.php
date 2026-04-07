<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionNotification;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

final readonly class PurchaseNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(private MessageBusInterface $bus) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PWC::eventName('completed') => 'onTransitionCompleted',
        ];
    }

    public function onTransitionCompleted(CompletedEvent $event): void
    {
        if (($event->getContext()['silent'] ?? false) === true) {
            return;
        }

        $aliases = $event->getMetadata('notifications', $event->getTransition());

        if (empty($handlers)) {
            return;
        }

        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        foreach ($aliases as $alias) {
            $this->bus->dispatch(new PurchaseTransitionNotification(
                purchaseId: $purchase->getId(),
                transition: $event->getTransition()->getName(),
                alias: $alias,
            ));
        }
    }
}
