<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Messenger\Stamp\LocaleStamp;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;


final readonly class PurchaseNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface     $bus,
        private PurchaseLocaleResolver  $purchaseLocaleResolver,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PWC::eventName('completed') => 'dispatchNotifications',
        ];
    }

    public function dispatchNotifications(CompletedEvent $event): void
    {
        if (($event->getContext()['silent'] ?? false) === true) {
            return;
        }

        $aliases = $event->getMetadata('notifications', $event->getTransition());

        if (empty($aliases)) {
            return;
        }

        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $locale = $this->purchaseLocaleResolver->resolve($purchase);

        foreach ($aliases as $alias) {
            $this->bus->dispatch(
                new PurchaseTransitionNotification(
                    purchaseId: $purchase->getId(),
                    transition: $event->getTransition()->getName(),
                    alias: $alias,
                ),
                [new LocaleStamp($locale)],
            );
        }
    }
}
