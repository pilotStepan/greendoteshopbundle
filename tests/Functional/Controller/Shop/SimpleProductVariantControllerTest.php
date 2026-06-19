<?php

namespace Greendot\EshopBundle\Tests\Functional\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\NotifyVariantAvailable;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Greendot\EshopBundle\Tests\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SimpleProductVariantControllerTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    public function testValidRequestReturns204AndDispatchesMessage(): void
    {
        $mockVariant = new ProductVariant();
        $mockRepo = $this->createMock(ProductVariantRepository::class);
        $mockRepo->method('find')->with(42)->willReturn($mockVariant);

        $mockBus = $this->createMock(MessageBusInterface::class);
        $mockBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn($msg) =>
                $msg instanceof NotifyVariantAvailable &&
                $msg->productVariantId === 42
            ))
            ->willReturn(new Envelope(new NotifyVariantAvailable(42)));

        $client = $this->createClientWithServices($mockRepo, $mockBus);

        $client->request(
            'POST',
            '/simple/api/product_variants/notify-watchdog',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['watchdog_type' => 'variant_available', 'product_variant_id' => 42]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testInvalidWatchdogTypeReturns400(): void
    {
        $mockBus = $this->createMock(MessageBusInterface::class);
        $mockBus->expects($this->never())->method('dispatch');

        $client = $this->createClientWithServices(bus: $mockBus);

        $client->request(
            'POST',
            '/simple/api/product_variants/notify-watchdog',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['watchdog_type' => 'unknown_type', 'product_variant_id' => 1]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testNonExistentVariantReturns400(): void
    {
        $mockRepo = $this->createMock(ProductVariantRepository::class);
        $mockRepo->method('find')->willReturn(null);

        $mockBus = $this->createMock(MessageBusInterface::class);
        $mockBus->expects($this->never())->method('dispatch');

        $client = $this->createClientWithServices($mockRepo, $mockBus);

        $client->request(
            'POST',
            '/simple/api/product_variants/notify-watchdog',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['watchdog_type' => 'variant_available', 'product_variant_id' => 9999]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testMissingProductVariantIdReturns400(): void
    {
        $mockRepo = $this->createMock(ProductVariantRepository::class);
        $mockRepo->method('find')->willReturn(null);

        $mockBus = $this->createMock(MessageBusInterface::class);
        $mockBus->expects($this->never())->method('dispatch');

        $client = $this->createClientWithServices($mockRepo, $mockBus);

        $client->request(
            'POST',
            '/simple/api/product_variants/notify-watchdog',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['watchdog_type' => 'variant_available']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function createClientWithServices(
        ?ProductVariantRepository $repo = null,
        ?MessageBusInterface $bus = null,
    ): KernelBrowser {
        $client = static::createClient();
        $container = static::getContainer();
        $container->set(ProductVariantRepository::class, $repo ?? $this->createMock(ProductVariantRepository::class));
        $container->set(MessageBusInterface::class, $bus ?? $this->createMock(MessageBusInterface::class));
        return $client;
    }
}
