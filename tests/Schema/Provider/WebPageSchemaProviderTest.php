<?php

namespace Greendot\EshopBundle\Tests\Schema\Provider;

use PHPUnit\Framework\TestCase;
use Greendot\EshopBundle\Enum\CategoryTypeEnum;
use Greendot\EshopBundle\Entity\Interface\PageableInterface;
use Greendot\EshopBundle\Schema\Provider\WebPageSchemaProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\CategoryType;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;

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

    public function testProvideUsesWebHomepageRouteForHomepageCategory(): void
    {
        $category = $this->createMock(CategoryEntity::class);
        $category->method('getId')->willReturn(1);
        $category->method('getName')->willReturn('Lepíky');
        $category->method('getDescription')->willReturn(null);

        $expectedUrl = 'https://example.com/';
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('web_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($expectedUrl);

        $array = $this->provider->provide($category)->toArray();

        $this->assertSame($expectedUrl, $array['url']);
        $this->assertSame($expectedUrl . '#webpage', $array['@id']);
    }

    public function testProvideUsesWebBlogDetailRouteForBlogCategory(): void
    {
        $categoryType = $this->createMock(CategoryType::class);
        $categoryType->method('getId')->willReturn(CategoryTypeEnum::BLOG->value);

        $category = $this->createMock(CategoryEntity::class);
        $category->method('getId')->willReturn(42);
        $category->method('getCategoryType')->willReturn($categoryType);
        $category->method('getSlug')->willReturn('jak-lepit-kov');
        $category->method('getName')->willReturn('Jak lepit kov');
        $category->method('getDescription')->willReturn(null);

        $expectedUrl = 'https://example.com/blog/jak-lepit-kov';
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('web_blog_detail', ['slug' => 'jak-lepit-kov'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($expectedUrl);

        $array = $this->provider->provide($category)->toArray();

        $this->assertSame($expectedUrl, $array['url']);
    }

    public function testProvideFallsBackToControllerNameForNonBlogCategory(): void
    {
        $categoryType = $this->createMock(CategoryType::class);
        $categoryType->method('getId')->willReturn(CategoryTypeEnum::CATEGORY->value);

        $category = $this->createMock(CategoryEntity::class);
        $category->method('getId')->willReturn(42);
        $category->method('getCategoryType')->willReturn($categoryType);
        $category->method('getControllerName')->willReturn('app_master');
        $category->method('getSlug')->willReturn('electronics');
        $category->method('getName')->willReturn('Electronics');
        $category->method('getDescription')->willReturn(null);

        $expectedUrl = 'https://example.com/electronics';
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('app_master', ['slug' => 'electronics'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($expectedUrl);

        $array = $this->provider->provide($category)->toArray();

        $this->assertSame($expectedUrl, $array['url']);
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
