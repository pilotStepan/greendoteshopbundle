<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Workflow\Action;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Messenger\Stamp\LocaleStamp;
use Greendot\EshopBundle\Notification\PurchaseTransitionNotification;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionActionResolver;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionActionSubscriber;
use Greendot\EshopBundle\Workflow\Action\ResolvedAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Drives a real Workflow so the subscriber is exercised through `workflow.purchase_flow.completed`.
 */
class PurchaseTransitionActionSubscriberTest extends TestCase
{
    use TransitionActionTestTrait;

    /** @var list<array{object, list<object>}> */
    private array $dispatched = [];

    private const METADATA = [
        'actions' => [
            'invoice.issue',
            ['action' => 'parcel.create', 'skippable' => true],
        ],
        'notifications' => ['purchase_notification.customer_email'],
    ];

    private function apply(array $metadata, array $context = [], ?\Greendot\EshopBundle\Entity\Project\Purchase $purchase = null): PurchaseTransitionActionResolver
    {
        $resolver = $this->buildResolver(
            ['invoice.issue' => $this->recordingAction('invoice.issue', 'Faktura'), 'parcel.create' => $this->recordingAction('parcel.create', 'Zásilka')],
            ['purchase_notification.customer_email' => $this->plainNotification()],
        );

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message, array $stamps) {
            $this->dispatched[] = [$message, $stamps];

            return new Envelope($message, $stamps);
        });

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PurchaseTransitionActionSubscriber($resolver, $bus, new PurchaseLocaleResolver('cs')));

        $workflow = $this->buildWorkflow($metadata, $dispatcher);
        $workflow->apply($purchase ?? $this->purchase(42), 'ship', $context);

        return $resolver;
    }

    public function testRunsActionsInOrderAndDispatchesNotificationsWithPurchaseLocale(): void
    {
        $purchase = $this->purchase(42);
        $purchase->setClient((new Client())->setLocale('sk'));

        $this->apply(self::METADATA, purchase: $purchase);

        $this->assertSame(['invoice.issue:ship', 'parcel.create:ship'], $this->executed());
        $this->assertCount(1, $this->dispatched);
        [$message, $stamps] = $this->dispatched[0];
        $this->assertInstanceOf(PurchaseTransitionNotification::class, $message);
        $this->assertSame([42, 'ship', 'purchase_notification.customer_email'], [$message->purchaseId, $message->transition, $message->alias]);
        $this->assertInstanceOf(LocaleStamp::class, $stamps[0]);
        $this->assertSame('sk', $stamps[0]->locale);
    }

    public function testNotificationKeyMetadataIsPassedToNotifications(): void
    {
        $this->apply(['notifications' => ['purchase_notification.customer_email'], 'notification_key' => 'log_send']);

        $this->assertSame('log_send', $this->dispatched[0][0]->transition);
    }

    public function testSilentSkipsEverySkippableAction(): void
    {
        $this->apply(self::METADATA, ['silent' => true]);

        $this->assertSame(['invoice.issue:ship'], $this->executed());
        $this->assertSame([], $this->dispatched);
    }

    public function testSkipActionsSkipsOnlyTheNamedOnes(): void
    {
        $this->apply(self::METADATA, ['skip_actions' => ['purchase_notification.customer_email']]);

        $this->assertSame(['invoice.issue:ship', 'parcel.create:ship'], $this->executed());
        $this->assertSame([], $this->dispatched);
    }

    public function testSkippingANonSkippableActionFails(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot skip invoice.issue');

        $this->apply(self::METADATA, ['skip_actions' => ['invoice.issue']]);
    }

    public function testTransitionWithoutActionsDoesNothing(): void
    {
        $this->apply([]);

        $this->assertSame([], $this->executed());
        $this->assertSame([], $this->dispatched);
    }

    /**
     * The preview lists exactly what the subscriber then runs.
     */
    public function testPreviewAndExecutionAgree(): void
    {
        $resolver = $this->apply(self::METADATA);

        $workflow = $this->buildWorkflow(self::METADATA);
        $previewed = array_map(
            static fn (ResolvedAction $a) => $a->entry->key,
            $resolver->resolve($this->purchase(), $workflow, $this->transition($workflow, 'ship')),
        );

        $ran = [...array_map(static fn (string $s) => explode(':', $s)[0], $this->executed()), ...array_map(static fn (array $d) => $d[0]->alias, $this->dispatched)];
        $this->assertSame($previewed, $ran);
    }
}
