<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

use LogicException;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Messenger\Stamp\LocaleStamp;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Greendot\EshopBundle\Notification\PurchaseTransitionNotification;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

/**
 * Runs the transition's configured actions (see PurchaseTransitionActionResolver) once the transition has completed.
 *
 * Context options:
 *  - `skip_actions`: list of action keys to skip; each must be skippable for the transition.
 *  - `silent`: true skips every skippable action.
 */
final readonly class PurchaseTransitionActionSubscriber implements EventSubscriberInterface
{
    public const CONTEXT_SKIP_ACTIONS = 'skip_actions';
    public const CONTEXT_SILENT = 'silent';

    public function __construct(
        private PurchaseTransitionActionResolver $resolver,
        private MessageBusInterface              $bus,
        private PurchaseLocaleResolver           $purchaseLocaleResolver,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PWC::eventName('completed') => 'runActions',
        ];
    }

    public function runActions(CompletedEvent $event): void
    {
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $workflow = $event->getWorkflow();
        $transition = $event->getTransition();
        if ($transition === null) {
            return;
        }

        $context = $event->getContext();
        $entries = $this->resolver->entries($workflow, $transition);
        $skip = self::skippedKeys($context, $entries, $transition->getName());

        foreach ($entries as $entry) {
            if (in_array($entry->key, $skip, true)) {
                continue;
            }

            match ($entry->kind) {
                ActionKind::ACTION => $this->resolver->getAction($entry->key)->execute($purchase, $transition->getName(), $context),
                ActionKind::NOTIFICATION => $this->bus->dispatch(
                    new PurchaseTransitionNotification(
                        purchaseId: $purchase->getId(),
                        transition: $this->resolver->notificationKey($workflow, $transition),
                        alias: $entry->key,
                    ),
                    [new LocaleStamp($this->purchaseLocaleResolver->resolve($purchase))],
                ),
            };
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param list<ActionEntry>    $entries
     *
     * @return list<string>
     *
     * @throws LogicException when `skip_actions` names an action that is not configured as skippable
     */
    public static function skippedKeys(array $context, array $entries, string $transitionName): array
    {
        $skippable = array_values(array_map(
            static fn (ActionEntry $e) => $e->key,
            array_filter($entries, static fn (ActionEntry $e) => $e->skippable),
        ));

        if (($context[self::CONTEXT_SILENT] ?? false) === true) {
            return $skippable;
        }

        $requested = $context[self::CONTEXT_SKIP_ACTIONS] ?? [];
        if (!is_array($requested)) {
            throw new LogicException('`skip_actions` must be a list of action keys.');
        }

        $notSkippable = array_diff($requested, $skippable);
        if ($notSkippable) {
            throw new LogicException(sprintf(
                'Transition "%s": cannot skip %s (not a skippable action of this transition).',
                $transitionName,
                implode(', ', $notSkippable),
            ));
        }

        return array_values($requested);
    }
}
