<?php

namespace Greendot\EshopBundle\Tests\MessageHandler\Watchdog\VariantAvailable;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Availability;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Watchdog;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\NotifyVariantAvailable;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\VariantAvailableEmail;
use Greendot\EshopBundle\MessageHandler\Watchdog\VariantAvailable\NotifyVariantAvailableHandler;
use Greendot\EshopBundle\Repository\Project\WatchdogRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class NotifyVariantAvailableHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private WatchdogRepository&MockObject $watchdogRepository;
    private MessageBusInterface&MockObject $messageBus;
    private NotifyVariantAvailableHandler $handler;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->watchdogRepository = $this->createMock(WatchdogRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->handler = new NotifyVariantAvailableHandler(
            $this->em,
            $this->logger,
            $this->watchdogRepository,
            $this->messageBus,
        );
    }

    public function testVariantNotFoundLogsWarningAndReturns(): void
    {
        $this->em->method('find')->willReturn(null);
        $this->logger->expects($this->once())->method('warning');
        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)(new NotifyVariantAvailable(999));
    }

    public function testNotPurchasableVariantSkipsNotification(): void
    {
        $variant = $this->makeVariant(id: 1, isPurchasable: false, availabilityId: 1);
        $this->em->method('find')->willReturn($variant);
        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)(new NotifyVariantAvailable(1));
    }

    public function testAvailabilityIdNotOneSkipsNotification(): void
    {
        $variant = $this->makeVariant(id: 1, isPurchasable: true, availabilityId: 2);
        $this->em->method('find')->willReturn($variant);
        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)(new NotifyVariantAvailable(1));
    }

    public function testNoActiveWatchdogsProducesNoDispatch(): void
    {
        $variant = $this->makeVariant(id: 1, isPurchasable: true, availabilityId: 1);
        $this->em->method('find')->willReturn($variant);
        $this->watchdogRepository->method('findActiveVariantAvailableByVariantId')->willReturn([]);
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new NotifyVariantAvailable(1));
    }

    public function testActiveWatchdogGetsQueuedAndEmailDispatched(): void
    {
        $variant = $this->makeVariant(id: 1, isPurchasable: true, availabilityId: 1);
        $watchdog = $this->makeWatchdog(id: 10, email: 'user@example.com');

        $this->em->method('find')->willReturn($variant);
        $this->watchdogRepository->method('findActiveVariantAvailableByVariantId')->willReturn([$watchdog]);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn($msg) =>
                $msg instanceof VariantAvailableEmail &&
                $msg->email === 'user@example.com' &&
                $msg->watchdogId === 10 &&
                $msg->productVariantId === 1
            ))
            ->willReturn(new Envelope(new VariantAvailableEmail(10, 1, 'user@example.com')));
        $this->em->expects($this->once())->method('flush');

        ($this->handler)(new NotifyVariantAvailable(1));

        $this->assertNotNull($watchdog->getQueuedAt());
    }

    public function testAlreadyQueuedWatchdogIsSkipped(): void
    {
        $variant = $this->makeVariant(id: 1, isPurchasable: true, availabilityId: 1);
        $watchdog = $this->makeWatchdog(id: 10, email: 'user@example.com');
        $watchdog->markQueued();

        $this->em->method('find')->willReturn($variant);
        $this->watchdogRepository->method('findActiveVariantAvailableByVariantId')->willReturn([$watchdog]);
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new NotifyVariantAvailable(1));
    }

    public function testCompletedWatchdogIsSkipped(): void
    {
        $variant = $this->makeVariant(id: 1, isPurchasable: true, availabilityId: 1);
        $watchdog = $this->makeWatchdog(id: 10, email: 'user@example.com');
        $watchdog->markCompleted();

        $this->em->method('find')->willReturn($variant);
        $this->watchdogRepository->method('findActiveVariantAvailableByVariantId')->willReturn([$watchdog]);
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new NotifyVariantAvailable(1));
    }

    public function testMultipleWatchdogsDispatchMultipleEmails(): void
    {
        $variant = $this->makeVariant(id: 1, isPurchasable: true, availabilityId: 1);
        $watchdogs = [
            $this->makeWatchdog(id: 1, email: 'a@example.com'),
            $this->makeWatchdog(id: 2, email: 'b@example.com'),
            $this->makeWatchdog(id: 3, email: 'c@example.com'),
        ];

        $this->em->method('find')->willReturn($variant);
        $this->watchdogRepository->method('findActiveVariantAvailableByVariantId')->willReturn($watchdogs);
        $this->messageBus->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->isInstanceOf(VariantAvailableEmail::class))
            ->willReturnCallback(fn($msg) => new Envelope($msg));
        $this->em->expects($this->once())->method('flush');

        ($this->handler)(new NotifyVariantAvailable(1));

        foreach ($watchdogs as $watchdog) {
            $this->assertNotNull($watchdog->getQueuedAt());
        }
    }

    private function makeVariant(int $id, bool $isPurchasable, int $availabilityId): ProductVariant
    {
        $availability = new Availability();
        $this->setPrivate($availability, 'id', $availabilityId);
        $availability->setIsPurchasable($isPurchasable);

        $variant = new ProductVariant();
        $this->setPrivate($variant, 'id', $id);
        $variant->setAvailability($availability);

        return $variant;
    }

    private function makeWatchdog(int $id, string $email): Watchdog
    {
        $watchdog = new Watchdog();
        $this->setPrivate($watchdog, 'id', $id);
        $watchdog->setEmail($email);
        return $watchdog;
    }

    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $prop = new \ReflectionProperty($object, $property);
        $prop->setValue($object, $value);
    }
}
