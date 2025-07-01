<?php

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Symfony\Component\Workflow\Event\CompletedEvent;

trait NotificationGuardTrait
{
    /**
     * Return the canonical event name used in the YAML map.
     */
    private function workflowEventName(CompletedEvent $e): string
    {
        return $e->getName(
            $e->getWorkflowName(),
            $e->getTransition()->getName()
        );
    }

    /**
     * Decide whether to send a notification.
     *
     * @param array<string,bool> $map YAML “notificationMap” injected into the subscriber.
     */
    private function shouldNotify(
        string $eventName,
        array  $context,
        array  $map,
    ): bool
    {
        if (($context['silent'] ?? false) === true) {
            return false;
        }
        return $map[$eventName] ?? false;
    }
}