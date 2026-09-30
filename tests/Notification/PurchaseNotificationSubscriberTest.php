<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Notification;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Messenger\Stamp\LocaleStamp;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Greendot\EshopBundle\Notification\PurchaseTransitionNotification;
use Greendot\EshopBundle\Notification\PurchaseNotificationSubscriber;

class PurchaseNotificationSubscriberTest extends TestCase
{
    private MessageBusInterface&\PHPUnit\Framework\MockObject\MockObject $bus;
    private PurchaseLocaleResolver $localeResolver;
    private PurchaseNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->localeResolver = new PurchaseLocaleResolver('cs');

        $this->subscriber = new PurchaseNotificationSubscriber($this->bus, $this->localeResolver);
    }

    public function testDispatchesOneMessagePerAliasEachStampedWithResolvedLocale(): void
    {
        $purchase = new Purchase();
        $purchase->setClient((new Client())->setLocale('sk'));
        $this->setPurchaseId($purchase, 42);

        $event = $this->buildCompletedEvent($purchase, ['customer_email', 'customer_sms']);

        $dispatched = [];
        $this->bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($message, array $stamps) use (&$dispatched) {
                $dispatched[] = [$message, $stamps];

                return new Envelope($message, $stamps);
            });

        $this->subscriber->dispatchNotifications($event);

        $this->assertCount(2, $dispatched);

        [$firstMessage, $firstStamps] = $dispatched[0];
        $this->assertInstanceOf(PurchaseTransitionNotification::class, $firstMessage);
        $this->assertSame('customer_email', $firstMessage->alias);
        $this->assertSame(42, $firstMessage->purchaseId);
        $this->assertSame('paid', $firstMessage->transition);
        $this->assertCount(1, $firstStamps);
        $this->assertInstanceOf(LocaleStamp::class, $firstStamps[0]);
        $this->assertSame('sk', $firstStamps[0]->locale);

        [$secondMessage, $secondStamps] = $dispatched[1];
        $this->assertSame('customer_sms', $secondMessage->alias);
        $this->assertSame('sk', $secondStamps[0]->locale);
    }

    public function testNoDispatchWhenTransitionHasNoNotificationsMetadata(): void
    {
        $purchase = new Purchase();

        $event = $this->buildCompletedEvent($purchase, []);

        $this->bus->expects($this->never())->method('dispatch');

        $this->subscriber->dispatchNotifications($event);
    }

    public function testNoDispatchWhenContextMarksTransitionSilent(): void
    {
        $purchase = new Purchase();

        $event = $this->buildCompletedEvent($purchase, ['customer_email'], context: ['silent' => true]);

        $this->bus->expects($this->never())->method('dispatch');

        $this->subscriber->dispatchNotifications($event);
    }

    /**
     * The stamp must actually carry the resolver's output, not a hardcoded
     * value - vary the resolved locale via the purchase and confirm it flows
     * through to the stamp.
     */
    public function testStampReflectsWhicheverLocaleTheResolverReturns(): void
    {
        $purchase = new Purchase();
        $purchase->setClient((new Client())->setLocale('de'));
        $this->setPurchaseId($purchase, 1);

        $event = $this->buildCompletedEvent($purchase, ['customer_email']);

        $seenLocale = null;
        $this->bus->method('dispatch')->willReturnCallback(function ($message, array $stamps) use (&$seenLocale) {
            $seenLocale = $stamps[0]->locale;

            return new Envelope($message, $stamps);
        });

        $this->subscriber->dispatchNotifications($event);

        $this->assertSame('de', $seenLocale);
    }

    private function buildCompletedEvent(Purchase $purchase, array $notificationAliases, array $context = []): CompletedEvent
    {
        $transition = new Transition('paid', 'received', 'completed');

        $metadataStore = $this->createMock(\Symfony\Component\Workflow\Metadata\MetadataStoreInterface::class);
        $metadataStore->method('getMetadata')
            ->with('notifications', $transition)
            ->willReturn($notificationAliases);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getMetadataStore')->willReturn($metadataStore);

        return new CompletedEvent($purchase, new Marking(), $transition, $workflow, $context);
    }

    private function setPurchaseId(Purchase $purchase, int $id): void
    {
        $reflection = new \ReflectionProperty(Purchase::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($purchase, $id);
    }
}
