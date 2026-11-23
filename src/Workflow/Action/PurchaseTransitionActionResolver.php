<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

use LogicException;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Notification\DescribableNotificationInterface;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * Reads what a purchase_flow transition does from its metadata. Both the executing subscriber and the CMS preview
 * go through here, so the preview always lists what will actually run.
 *
 * Metadata keys:
 *  - `actions`: list of keys, or `{action: <key>, skippable: <bool>}`. A key names a PurchaseActionInterface
 *    service or a notification handler alias (`purchase_notification.*`). Notifications are skippable by default,
 *    actions are not.
 *  - `notifications` (legacy): list of notification handler aliases, all skippable. Runs after `actions`.
 *  - `notification_key`: the transition name passed to notification handlers (templates, subjects).
 */
final readonly class PurchaseTransitionActionResolver
{
    /**
     * @param iterable<PurchaseTransitionEffectDescriberInterface> $effectDescribers
     */
    public function __construct(
        #[AutowireLocator(PurchaseActionInterface::TAG)]
        private ContainerInterface $actions,
        #[AutowireLocator('greendot_eshop.purchase_notification')]
        private ContainerInterface $notifications,
        #[AutowireIterator(PurchaseTransitionEffectDescriberInterface::TAG)]
        private iterable           $effectDescribers,
    ) {}

    /**
     * @return list<ActionEntry>
     *
     * @throws LogicException on malformed metadata or an unknown key
     */
    public function entries(WorkflowInterface $workflow, Transition $transition): array
    {
        $metadata = $workflow->getMetadataStore()->getTransitionMetadata($transition);
        $entries = [];

        foreach ($this->listMetadata($metadata, 'actions', $transition) as $raw) {
            $entries[] = $this->parseActionEntry($raw, $transition);
        }

        foreach ($this->listMetadata($metadata, 'notifications', $transition) as $alias) {
            if (!is_string($alias) || !$this->notifications->has($alias)) {
                throw new LogicException(sprintf(
                    'Transition "%s": unknown notification "%s" in `notifications` metadata.',
                    $transition->getName(),
                    is_string($alias) ? $alias : get_debug_type($alias),
                ));
            }
            $entries[] = new ActionEntry($alias, ActionKind::NOTIFICATION, true);
        }

        $keys = array_map(static fn (ActionEntry $e) => $e->key, $entries);
        $duplicates = array_keys(array_filter(array_count_values($keys), static fn (int $n) => $n > 1));
        if ($duplicates) {
            throw new LogicException(sprintf(
                'Transition "%s" lists %s more than once (`actions` and `notifications` together).',
                $transition->getName(),
                implode(', ', $duplicates),
            ));
        }

        return $entries;
    }

    /**
     * The configured actions that apply to this purchase, in execution order.
     *
     * @return list<ResolvedAction>
     */
    public function resolve(Purchase $purchase, WorkflowInterface $workflow, Transition $transition): array
    {
        $resolved = [];

        foreach ($this->entries($workflow, $transition) as $entry) {
            $description = match ($entry->kind) {
                ActionKind::ACTION => $this->getAction($entry->key)->describe($purchase, $transition->getName()),
                ActionKind::NOTIFICATION => $this->describeNotification($entry->key, $purchase, $this->notificationKey($workflow, $transition)),
            };

            if ($description !== null) {
                $resolved[] = new ResolvedAction($entry, $description);
            }
        }

        return $resolved;
    }

    /**
     * Built-in effects of code subscribers; always run, cannot be skipped.
     *
     * @return list<ActionDescription>
     */
    public function systemEffects(Purchase $purchase, WorkflowInterface $workflow, Transition $transition): array
    {
        $effects = [];
        foreach ($this->effectDescribers as $describer) {
            foreach ($describer->describeEffects($purchase, $transition, $workflow) as $effect) {
                $effects[] = $effect;
            }
        }

        return $effects;
    }

    /**
     * The enabled transition of that name, or else its first definition (so blocked transitions can still be described).
     *
     * @throws InvalidArgumentException when the workflow has no transition of that name
     */
    public function findTransition(Purchase $purchase, WorkflowInterface $workflow, string $name): Transition
    {
        foreach ($workflow->getEnabledTransitions($purchase) as $transition) {
            if ($transition->getName() === $name) {
                return $transition;
            }
        }

        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            if ($transition->getName() === $name) {
                return $transition;
            }
        }

        throw new InvalidArgumentException(sprintf('Workflow "%s" has no transition "%s".', $workflow->getName(), $name));
    }

    public function notificationHandler(string $alias): PurchaseNotificationHandlerInterface
    {
        $handler = $this->notifications->get($alias);
        if (!$handler instanceof PurchaseNotificationHandlerInterface) {
            throw new LogicException(sprintf('Service "%s" must implement %s.', $alias, PurchaseNotificationHandlerInterface::class));
        }

        return $handler;
    }

    public function notificationKey(WorkflowInterface $workflow, Transition $transition): string
    {
        $key = $workflow->getMetadataStore()->getMetadata('notification_key', $transition);

        return is_string($key) ? $key : $transition->getName();
    }

    public function getAction(string $key): PurchaseActionInterface
    {
        $action = $this->actions->get($key);
        if (!$action instanceof PurchaseActionInterface) {
            throw new LogicException(sprintf('Service "%s" must implement %s.', $key, PurchaseActionInterface::class));
        }

        return $action;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return list<mixed>
     */
    private function listMetadata(array $metadata, string $key, Transition $transition): array
    {
        $value = $metadata[$key] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            throw new LogicException(sprintf('Transition "%s": `%s` metadata must be a list.', $transition->getName(), $key));
        }

        return $value;
    }

    private function parseActionEntry(mixed $raw, Transition $transition): ActionEntry
    {
        $skippable = null;
        if (is_array($raw)) {
            $unknownOptions = array_diff(array_keys($raw), ['action', 'skippable']);
            if ($unknownOptions || !is_string($raw['action'] ?? null) || !is_bool($raw['skippable'] ?? false)) {
                throw new LogicException(sprintf(
                    'Transition "%s": an `actions` entry must be a key or {action: <key>, skippable: <bool>}.',
                    $transition->getName(),
                ));
            }
            $skippable = $raw['skippable'] ?? null;
            $raw = $raw['action'];
        }

        if (!is_string($raw)) {
            throw new LogicException(sprintf('Transition "%s": `actions` entries must be strings or maps.', $transition->getName()));
        }

        $kind = match (true) {
            $this->actions->has($raw) => ActionKind::ACTION,
            $this->notifications->has($raw) => ActionKind::NOTIFICATION,
            default => throw new LogicException(sprintf(
                'Transition "%s": unknown action "%s". Register a %s with #[AsTaggedItem(index: \'%2$s\')], or use a notification alias.',
                $transition->getName(),
                $raw,
                PurchaseActionInterface::class,
            )),
        };

        return new ActionEntry($raw, $kind, $skippable ?? $kind === ActionKind::NOTIFICATION);
    }

    private function describeNotification(string $alias, Purchase $purchase, string $notificationKey): ?ActionDescription
    {
        $handler = $this->notificationHandler($alias);

        if ($handler instanceof DescribableNotificationInterface) {
            return $handler->describe($purchase, $notificationKey);
        }

        return new ActionDescription($alias);
    }
}
