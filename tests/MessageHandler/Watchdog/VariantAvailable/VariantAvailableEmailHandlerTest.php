<?php

namespace Greendot\EshopBundle\Tests\MessageHandler\Watchdog\VariantAvailable;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Watchdog;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogState;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\VariantAvailableEmail;
use Greendot\EshopBundle\MessageHandler\Watchdog\VariantAvailable\VariantAvailableEmailHandler;
use Greendot\EshopBundle\Service\ManageMails;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class VariantAvailableEmailHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ManageMails&MockObject $manageMails;
    private LoggerInterface&MockObject $logger;
    private VariantAvailableEmailHandler $handler;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->manageMails = $this->createMock(ManageMails::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new VariantAvailableEmailHandler(
            $this->em,
            $this->manageMails,
            $this->logger,
        );
    }

    public function testWatchdogNotFoundThrowsUnrecoverable(): void
    {
        $this->em->method('find')->willReturn(null);

        $this->expectException(UnrecoverableMessageHandlingException::class);

        ($this->handler)(new VariantAvailableEmail(999, 1, 'user@example.com'));
    }

    public function testCompletedWatchdogReturnsEarly(): void
    {
        $watchdog = $this->makeWatchdog(id: 1, email: 'user@example.com');
        $watchdog->markCompleted();

        $this->em->method('find')->willReturn($watchdog);
        $this->manageMails->expects($this->never())->method('sendTemplate');
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new VariantAvailableEmail(1, 42, 'user@example.com'));
    }

    public function testCanceledWatchdogReturnsEarly(): void
    {
        $watchdog = $this->makeWatchdog(id: 1, email: 'user@example.com');
        $watchdog->setState(WatchdogState::Canceled);

        $this->em->method('find')->willReturn($watchdog);
        $this->manageMails->expects($this->never())->method('sendTemplate');
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new VariantAvailableEmail(1, 42, 'user@example.com'));
    }

    public function testVariantNotFoundThrowsUnrecoverable(): void
    {
        $watchdog = $this->makeWatchdog(id: 1, email: 'user@example.com');

        $this->em->method('find')->willReturnCallback(
            fn(string $class) => $class === Watchdog::class ? $watchdog : null
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        ($this->handler)(new VariantAvailableEmail(1, 999, 'user@example.com'));
    }

    public function testHappyPathSendsEmailAndMarksCompleted(): void
    {
        $watchdog = $this->makeWatchdog(id: 1, email: 'user@example.com');
        $variant = $this->makeVariant(id: 42, name: 'Blue Widget', productName: 'Widget', productSlug: 'widget');

        $this->setupFindReturns($watchdog, $variant);
        $this->manageMails->method('getBaseTemplate')->willReturn(new TemplatedEmail());
        $this->manageMails->expects($this->once())->method('sendTemplate');
        $this->em->expects($this->once())->method('flush');

        ($this->handler)(new VariantAvailableEmail(1, 42, 'user@example.com'));

        $this->assertSame(WatchdogState::Completed, $watchdog->getState());
        $this->assertNotNull($watchdog->getCompletedAt());
    }

    public function testEmailAddressFromMessageIsUsed(): void
    {
        $watchdog = $this->makeWatchdog(id: 1, email: 'user@example.com');
        $variant = $this->makeVariant(id: 42, name: 'Blue Widget', productName: 'Widget', productSlug: 'widget');

        $this->setupFindReturns($watchdog, $variant);
        $this->manageMails->method('getBaseTemplate')->willReturn(new TemplatedEmail());

        $sentEmail = null;
        $this->manageMails->method('sendTemplate')
            ->willReturnCallback(function (TemplatedEmail $email) use (&$sentEmail): void {
                $sentEmail = $email;
            });

        ($this->handler)(new VariantAvailableEmail(1, 42, 'user@example.com'));

        $this->assertNotNull($sentEmail);
        $addresses = $sentEmail->getTo();
        $this->assertCount(1, $addresses);
        $this->assertSame('user@example.com', $addresses[0]->getAddress());
    }

    public function testEmailSubjectIsSet(): void
    {
        $watchdog = $this->makeWatchdog(id: 1, email: 'user@example.com');
        $variant = $this->makeVariant(id: 42, name: 'Blue Widget', productName: 'Widget', productSlug: 'widget');

        $this->setupFindReturns($watchdog, $variant);
        $this->manageMails->method('getBaseTemplate')->willReturn(new TemplatedEmail());

        $sentEmail = null;
        $this->manageMails->method('sendTemplate')
            ->willReturnCallback(function (TemplatedEmail $email) use (&$sentEmail): void {
                $sentEmail = $email;
            });

        ($this->handler)(new VariantAvailableEmail(1, 42, 'user@example.com'));

        $this->assertSame('Produkt je opět dostupný', $sentEmail->getSubject());
    }

    public function testEmailContextContainsVariantAndProductData(): void
    {
        $watchdog = $this->makeWatchdog(id: 1, email: 'user@example.com');
        $variant = $this->makeVariant(id: 42, name: 'Blue Widget', productName: 'Widget Pro', productSlug: 'widget-pro');

        $this->setupFindReturns($watchdog, $variant);
        $this->manageMails->method('getBaseTemplate')->willReturn(new TemplatedEmail());

        $sentEmail = null;
        $this->manageMails->method('sendTemplate')
            ->willReturnCallback(function (TemplatedEmail $email) use (&$sentEmail): void {
                $sentEmail = $email;
            });

        ($this->handler)(new VariantAvailableEmail(1, 42, 'user@example.com'));

        $context = $sentEmail->getContext();
        $this->assertArrayHasKey('data', $context);
        $this->assertSame('Blue Widget', $context['data']['variant_name']);
        $this->assertSame('Widget Pro', $context['data']['product_name']);
        $this->assertSame('widget-pro', $context['data']['product_slug']);
    }

    private function setupFindReturns(Watchdog $watchdog, ProductVariant $variant): void
    {
        $this->em->method('find')->willReturnCallback(
            fn(string $class) => match ($class) {
                Watchdog::class => $watchdog,
                ProductVariant::class => $variant,
                default => null,
            }
        );
    }

    private function makeWatchdog(int $id, string $email): Watchdog
    {
        $watchdog = new Watchdog();
        $this->setPrivate($watchdog, 'id', $id);
        $watchdog->setEmail($email);
        return $watchdog;
    }

    private function makeVariant(int $id, string $name, string $productName, string $productSlug): ProductVariant
    {
        $product = new Product();
        $product->setName($productName);
        $product->setSlug($productSlug);

        $variant = new ProductVariant();
        $this->setPrivate($variant, 'id', $id);
        $variant->setName($name);
        $variant->setProduct($product);

        return $variant;
    }

    private function setPrivate(object $object, string $property, mixed $value): void
    {
        (new \ReflectionProperty($object, $property))->setValue($object, $value);
    }
}
