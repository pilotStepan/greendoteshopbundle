<?php

namespace Greendot\EshopBundle\Tests\Schema\Provider;

use PHPUnit\Framework\TestCase;
use Greendot\EshopBundle\Entity\Interface\PageableInterface;
use Greendot\EshopBundle\Schema\Provider\WebPageSchemaProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WebPageSchemaProviderTest extends TestCase
{
    private UrlGeneratorInterface $urlGenerator;
    private WebPageSchemaProvider $provider;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->provider = new WebPageSchemaProvider($this->urlGenerator);
    }

    public function testSupportsReturnsTrueForPageableObject(): void
    {
        $pageable = $this->createPageableStub();
        $this->assertTrue($this->provider->supports($pageable));
    }

    public function testSupportsReturnsFalseForNonPageable(): void
    {
        $this->assertFalse($this->provider->supports(new \stdClass()));
        $this->assertFalse($this->provider->supports(null));
    }

    public function testProvideCreatesWebPageWithCorrectFields(): void
    {
        $pageable = $this->createPageableStub(
            controllerName: 'category_show',
            slug: 'electronics',
            name: 'Electronics',
            description: 'All electronics',
        );

        $expectedUrl = 'https://example.com/electronics';
        $this->urlGenerator
            ->method('generate')
            ->with('category_show', ['slug' => 'electronics'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($expectedUrl);

        $schema = $this->provider->provide($pageable);
        $array = $schema->toArray();

        $this->assertSame('WebPage', $array['@type']);
        $this->assertSame($expectedUrl . '#webpage', $array['@id']);
        $this->assertSame($expectedUrl, $array['url']);
        $this->assertSame('Electronics', $array['name']);
        $this->assertSame('All electronics', $array['description']);
    }

    private function createPageableStub(
        string  $controllerName = 'route',
        string  $slug = 'slug',
        string  $name = 'Name',
        ?string $description = null,
    ): object {
        return new class($controllerName, $slug, $name, $description) implements PageableInterface {
            public function __construct(
                private string  $controllerName,
                private string  $slug,
                private string  $name,
                private ?string $description,
            ) {}

            public function getControllerName(): string { return $this->controllerName; }
            public function getSlug(): string { return $this->slug; }
            public function getTitle(): ?string { return $this->name; }
            public function getDescription(): ?string { return $this->description; }
            // Provider calls getName(), which Category implements alongside PageableInterface
            public function getName(): string { return $this->name; }
        };
    }
}
