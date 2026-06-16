<?php

namespace Greendot\EshopBundle\Tests\Schema;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\SchemaOrg\WebPage;
use Spatie\SchemaOrg\Schema;
use Greendot\EshopBundle\Schema\SchemaRegistry;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;

class SchemaRegistryTest extends TestCase
{
    public function testCollectCallsProvideOnSupportedProvider(): void
    {
        $schema = Schema::webPage()->name('Test');
        $provider = $this->makeProvider(supports: true, provides: $schema, priority: 0);

        $registry = new SchemaRegistry([$provider]);
        $registry->collect(new \stdClass());

        $this->assertStringContainsString('WebPage', $registry->render());
    }

    public function testCollectSkipsUnsupportedProvider(): void
    {
        $provider = $this->createMock(SchemaProviderInterface::class);
        $provider->method('supports')->willReturn(false);
        $provider->method('getPriority')->willReturn(0);
        $provider->expects($this->never())->method('provide');

        $registry = new SchemaRegistry([$provider]);
        $registry->collect(new \stdClass());

        $this->assertSame('', $registry->render());
    }

    public function testCollectCallsAllSupportedProviders(): void
    {
        $schemaA = Schema::webPage()->name('Page A');
        $schemaB = Schema::article()->name('Article B');

        $providerA = $this->makeProvider(supports: true, provides: $schemaA, priority: 0);
        $providerB = $this->makeProvider(supports: true, provides: $schemaB, priority: 0);

        $registry = new SchemaRegistry([$providerA, $providerB]);
        $registry->collect(new \stdClass());

        $output = $registry->render();
        $this->assertStringContainsString('WebPage', $output);
        $this->assertStringContainsString('Article', $output);
    }

    public function testDuplicateSchemaInstanceIsStoredOnce(): void
    {
        $schema = Schema::webPage()->name('Shared');

        $providerA = $this->makeProvider(supports: true, provides: $schema, priority: 0);
        $providerB = $this->makeProvider(supports: true, provides: $schema, priority: 0);

        $registry = new SchemaRegistry([$providerA, $providerB]);
        $registry->collect(new \stdClass());

        // Single object → JSON encodes as object not array
        $rendered = $registry->render();
        $this->assertStringContainsString('<script type="application/ld+json">', $rendered);
        // If deduplicated, JSON is a single object (no leading '[')
        $json = trim(strip_tags($rendered));
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('@type', $decoded, 'Expected single schema object, not an array of schemas');
    }

    public function testDistinctSchemasAreBothStored(): void
    {
        $schemaA = Schema::webPage()->name('A');
        $schemaB = Schema::article()->name('B');

        $providerA = $this->makeProvider(supports: true, provides: $schemaA, priority: 0);
        $providerB = $this->makeProvider(supports: true, provides: $schemaB, priority: 0);

        $registry = new SchemaRegistry([$providerA, $providerB]);
        $registry->collect(new \stdClass());

        $json = trim(strip_tags($registry->render()));
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
    }

    public function testRenderOutputsJsonLdScriptTag(): void
    {
        $provider = $this->makeProvider(supports: true, provides: Schema::webPage()->name('SEO'), priority: 0);

        $registry = new SchemaRegistry([$provider]);
        $registry->collect(new \stdClass());

        $output = $registry->render();
        $this->assertStringContainsString('<script type="application/ld+json">', $output);
        $this->assertStringContainsString('</script>', $output);
    }

    public function testRenderReturnsEmptyStringWhenNoSchemasCollected(): void
    {
        $registry = new SchemaRegistry([]);
        $this->assertSame('', $registry->render());
    }

    public function testCollectRespectsPriorityOrder(): void
    {
        $callOrder = [];

        $highPriority = $this->createMock(SchemaProviderInterface::class);
        $highPriority->method('getPriority')->willReturn(10);
        $highPriority->method('supports')->willReturnCallback(function () use (&$callOrder) {
            $callOrder[] = 'high';
            return false;
        });

        $lowPriority = $this->createMock(SchemaProviderInterface::class);
        $lowPriority->method('getPriority')->willReturn(0);
        $lowPriority->method('supports')->willReturnCallback(function () use (&$callOrder) {
            $callOrder[] = 'low';
            return false;
        });

        $registry = new SchemaRegistry([$lowPriority, $highPriority]);
        $registry->collect(new \stdClass());

        $this->assertSame(['high', 'low'], $callOrder, 'Higher priority provider should be called first');
    }

    private function makeProvider(bool $supports, mixed $provides, int $priority): SchemaProviderInterface&MockObject
    {
        $mock = $this->createMock(SchemaProviderInterface::class);
        $mock->method('supports')->willReturn($supports);
        $mock->method('provide')->willReturn($provides);
        $mock->method('getPriority')->willReturn($priority);
        return $mock;
    }
}
