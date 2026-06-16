<?php

namespace Greendot\EshopBundle\Tests\Schema\Provider;

use PHPUnit\Framework\TestCase;
use Greendot\EshopBundle\Schema\Context\BreadcrumbSchemaContext;
use Greendot\EshopBundle\Schema\Provider\DefaultBreadcrumbSchemaProvider;

class DefaultBreadcrumbSchemaProviderTest extends TestCase
{
    private DefaultBreadcrumbSchemaProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new DefaultBreadcrumbSchemaProvider();
    }

    public function testSupportsReturnsTrueForBreadcrumbContext(): void
    {
        $this->assertTrue($this->provider->supports(new BreadcrumbSchemaContext([])));
    }

    public function testSupportsReturnsFalseForOtherObject(): void
    {
        $this->assertFalse($this->provider->supports(new \stdClass()));
        $this->assertFalse($this->provider->supports(null));
        $this->assertFalse($this->provider->supports('string'));
    }

    public function testProvideCreatesListItemsWithSequentialPositions(): void
    {
        $ctx = new BreadcrumbSchemaContext([
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Category', 'url' => '/category'],
        ]);

        $schema = $this->provider->provide($ctx);
        $array = $schema->toArray();

        $items = $array['itemListElement'];
        $this->assertCount(2, $items);
        $this->assertSame(1, $items[0]['position']);
        $this->assertSame('Home', $items[0]['name']);
        $this->assertSame(2, $items[1]['position']);
        $this->assertSame('Category', $items[1]['name']);
    }

    public function testProvideStripsHtmlTagsFromItemName(): void
    {
        $ctx = new BreadcrumbSchemaContext([
            ['name' => '<b>Bold Product</b>', 'url' => '/product'],
        ]);

        $schema = $this->provider->provide($ctx);
        $array = $schema->toArray();

        $this->assertSame('Bold Product', $array['itemListElement'][0]['name']);
    }

    public function testProvideWithEmptyItemsReturnsEmptyBreadcrumbList(): void
    {
        $ctx = new BreadcrumbSchemaContext([]);
        $schema = $this->provider->provide($ctx);
        $array = $schema->toArray();

        $this->assertSame('BreadcrumbList', $array['@type']);
        $this->assertEmpty($array['itemListElement'] ?? []);
    }
}
