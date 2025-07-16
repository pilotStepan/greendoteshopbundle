<?php

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Contracts\EventDispatcher\Event;

trait NotificationGuardTrait
{
    /**
     * Decide whether to send a notification.
     * @param array<string,bool> $map YAML "notificationMap" injected into the subscriber.
     */
    private function shouldNotify(Event $event, array $map): bool
    {
        $name = $this->resolveEventName($event);
        $context = $this->resolveEventContext($event);

        if (($context['silent'] ?? false) === true) {
            return false;
        }
        return $map[$name] ?? false;
    }

    private function resolveEventName(Event $event): string
    {
        return match (true) {
            $event instanceof CompletedEvent => $event->getName(
                $event->getWorkflowName(),
                $event->getTransition()->getName()
            ),
            default => $event::class,
        };
    }

    private function resolveEventContext(Event $event): array
    {
        return match (true) {
            $event instanceof CompletedEvent => $event->getContext(),
            default => [],
        };
    }
}