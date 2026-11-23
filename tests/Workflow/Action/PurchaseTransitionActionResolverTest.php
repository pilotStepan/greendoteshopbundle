<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Workflow\Action;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Notification\DescribableNotificationInterface;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\ActionEntry;
use Greendot\EshopBundle\Workflow\Action\ActionKind;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionEffectDescriberInterface;
use Greendot\EshopBundle\Workflow\Action\ResolvedAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

class PurchaseTransitionActionResolverTest extends TestCase
{
    use TransitionActionTestTrait;

    public function testParsesActionsThenLegacyNotificationsWithDefaultSkippability(): void
    {
        $workflow = $this->buildWorkflow([
            'actions' => [
                'invoice.issue',
                ['action' => 'parcel.create', 'skippable' => true],
                'purchase_notification.customer_email',
            ],
            'notifications' => ['purchase_notification.company_email'],
        ]);
        $resolver = $this->buildResolver(
            ['invoice.issue' => $this->recordingAction('invoice.issue'), 'parcel.create' => $this->recordingAction('parcel.create')],
            ['purchase_notification.customer_email' => $this->plainNotification(), 'purchase_notification.company_email' => $this->plainNotification()],
        );

        $entries = $resolver->entries($workflow, $this->transition($workflow, 'ship'));

        $this->assertEquals([
            new ActionEntry('invoice.issue', ActionKind::ACTION, false),
            new ActionEntry('parcel.create', ActionKind::ACTION, true),
            new ActionEntry('purchase_notification.customer_email', ActionKind::NOTIFICATION, true),
            new ActionEntry('purchase_notification.company_email', ActionKind::NOTIFICATION, true),
        ], $entries);
    }

    public function testNotificationCanBeMadeNonSkippable(): void
    {
        $workflow = $this->buildWorkflow(['actions' => [['action' => 'purchase_notification.customer_email', 'skippable' => false]]]);
        $resolver = $this->buildResolver([], ['purchase_notification.customer_email' => $this->plainNotification()]);

        $this->assertFalse($resolver->entries($workflow, $this->transition($workflow, 'ship'))[0]->skippable);
    }

    public function testTransitionWithoutMetadataHasNoEntries(): void
    {
        $workflow = $this->buildWorkflow([]);

        $this->assertSame([], $this->buildResolver()->entries($workflow, $this->transition($workflow, 'other')));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidMetadata(): iterable
    {
        yield 'unknown action' => [['actions' => ['nope']], 'unknown action "nope"'];
        yield 'unknown notification' => [['notifications' => ['purchase_notification.nope']], 'unknown notification "purchase_notification.nope"'];
        yield 'actions not a list' => [['actions' => 'invoice.issue'], '`actions` metadata must be a list'];
        yield 'unknown option' => [['actions' => [['action' => 'invoice.issue', 'async' => true]]], 'must be a key or {action'];
        yield 'non-bool skippable' => [['actions' => [['action' => 'invoice.issue', 'skippable' => 'yes']]], 'must be a key or {action'];
        yield 'duplicate across keys' => [
            ['actions' => ['purchase_notification.customer_email'], 'notifications' => ['purchase_notification.customer_email']],
            'lists purchase_notification.customer_email more than once',
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidMetadata')]
    public function testRejectsInvalidMetadata(array $metadata, string $message): void
    {
        $workflow = $this->buildWorkflow($metadata);
        $resolver = $this->buildResolver(
            ['invoice.issue' => $this->recordingAction('invoice.issue')],
            ['purchase_notification.customer_email' => $this->plainNotification()],
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        $resolver->entries($workflow, $this->transition($workflow, 'ship'));
    }

    public function testResolveDropsInapplicableActionsAndDescribesNotifications(): void
    {
        $describable = new class implements PurchaseNotificationHandlerInterface, DescribableNotificationInterface {
            public ?string $seenTransition = null;

            public function handle(Purchase $purchase, string $transition): void {}

            public function describe(Purchase $purchase, string $transition): ActionDescription
            {
                $this->seenTransition = $transition;

                return new ActionDescription('E-mail', category: ActionCategory::EMAIL);
            }
        };

        $workflow = $this->buildWorkflow([
            'actions' => ['applies', 'inapplicable', 'purchase_notification.described', 'purchase_notification.plain'],
            'notification_key' => 'log_send',
        ]);
        $resolver = $this->buildResolver(
            ['applies' => $this->recordingAction('applies', 'Applies'), 'inapplicable' => $this->recordingAction('inapplicable')],
            ['purchase_notification.described' => $describable, 'purchase_notification.plain' => $this->plainNotification()],
        );

        $resolved = $resolver->resolve($this->purchase(), $workflow, $this->transition($workflow, 'ship'));

        $this->assertSame(
            [['applies', 'Applies'], ['purchase_notification.described', 'E-mail'], ['purchase_notification.plain', 'purchase_notification.plain']],
            array_map(static fn (ResolvedAction $a) => [$a->entry->key, $a->description->label], $resolved),
        );
        $this->assertSame('log_send', $describable->seenTransition, 'notifications are described with the notification key');
    }

    public function testSystemEffectsCollectEveryDescriber(): void
    {
        $describer = static fn (string $label) => new class($label) implements PurchaseTransitionEffectDescriberInterface {
            public function __construct(private string $label) {}

            public function describeEffects(Purchase $purchase, Transition $transition, WorkflowInterface $workflow): iterable
            {
                yield new ActionDescription($this->label . ':' . $transition->getName());
            }
        };

        $workflow = $this->buildWorkflow([]);
        $resolver = $this->buildResolver(describers: [$describer('a'), $describer('b')]);

        $effects = $resolver->systemEffects($this->purchase(), $workflow, $this->transition($workflow, 'ship'));

        $this->assertSame(['a:ship', 'b:ship'], array_map(static fn (ActionDescription $d) => $d->label, $effects));
    }
}
