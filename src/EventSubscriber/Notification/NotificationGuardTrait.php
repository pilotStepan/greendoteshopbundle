<?php

namespace Greendot\EshopBundle\EventSubscriber\Notification;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\Workflow\Event\CompletedEvent;

trait NotificationGuardTrait
{
    /**
     * Decide whether to send a notification.
     * @param array<string,bool> $map YAML "notificationMap" injected into the subscriber.
     * @deprecated Use transition metadata `client_notifications` via shouldNotifyChannel().
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

    /**
     * Resolve channel-specific notification strategy from workflow transition metadata.
     * Falls back to the existing map-based behavior when metadata is not provided.
     *
     * @param array<string,bool> $map YAML "notificationMap" injected into the subscriber.
     */
    private function shouldNotifyChannel(
        CompletedEvent $event,
        array          $map,
        string         $channel,
        string         $metadataKey = 'client_notifications',
    ): bool
    {
        $context = $event->getContext();
        if (($context['silent'] ?? false) === true) {
            return false;
        }

        $channels = $this->resolveNotificationChannels($event, $metadataKey);
        if ($channels === null) {
            return $this->shouldNotify($event, $map);
        }

        return in_array(strtolower($channel), $channels, true);
    }

    /**
     * @return list<string>|null Returns null when metadata key is not defined.
     */
    private function resolveNotificationChannels(CompletedEvent $event, string $metadataKey): ?array
    {
        $value = $event->getMetadata($metadataKey, $event->getTransition());
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $channels = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $normalized = strtolower(trim($item));
            if ($normalized !== '') {
                $channels[] = $normalized;
            }
        }

        return $channels;
    }

    private function resolveEventName(Event $event): string
    {
        return match (true) {
            $event instanceof CompletedEvent => $event->getName(
                $event->getWorkflowName(),
                $event->getTransition()->getName(),
            ),
            default                          => $event::class,
        };
    }

    private function resolveEventContext(Event $event): array
    {
        return match (true) {
            $event instanceof CompletedEvent => $event->getContext(),
            default                          => [],
        };
    }
}