<?php

namespace Greendot\EshopBundle\Tests\Schema\Provider;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\Enum\CategoryTypeEnum;
use Greendot\EshopBundle\Entity\Project\Label;
use Greendot\EshopBundle\Entity\Project\Upload;
use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Entity\Project\CategoryType;
use Greendot\EshopBundle\Entity\Project\CategoryPerson;
use Greendot\EshopBundle\Schema\Provider\ArticleSchemaProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;

class ArticleSchemaProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private ArticleSchemaProvider $provider;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->provider = new ArticleSchemaProvider($this->urlGenerator, 'https://example.com');
    }

    public function testSupportsReturnsTrueForBlogCategory(): void
    {
        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG);
        $this->assertTrue($this->provider->supports($category));
    }

    public function testSupportsReturnsFalseForNonBlogCategory(): void
    {
        $category = $this->createCategoryStub(CategoryTypeEnum::CATEGORY);
        $this->assertFalse($this->provider->supports($category));
    }

    public function testSupportsReturnsFalseForNonCategoryObject(): void
    {
        $this->assertFalse($this->provider->supports(new \stdClass()));
        $this->assertFalse($this->provider->supports(null));
    }

    public function testProvideBuildsArticleWithExpectedFields(): void
    {
        $category = $this->createCategoryStub(
            CategoryTypeEnum::BLOG,
            slug: 'jak-lepit-kov',
            name: 'Jak lepit kov',
            title: 'Jak lepit kov | Blog',
            description: '<p>Popis <b>článku</b></p>',
            html: '<p>Text <i>článku</i></p>',
        );

        $expectedUrl = 'https://example.com/blog/jak-lepit-kov';
        $this->urlGenerator
            ->method('generate')
            ->with('web_blog_detail', ['slug' => 'jak-lepit-kov'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($expectedUrl);

        $schema = $this->provider->provide($category);
        $array = $schema->toArray();

        $this->assertSame('Article', $array['@type']);
        $this->assertSame($expectedUrl . '#article', $array['@id']);
        $this->assertSame($expectedUrl, $array['url']);
        $this->assertSame('Jak lepit kov | Blog', $array['headline']);
        $this->assertSame('Jak lepit kov', $array['name']);
        $this->assertSame('Popis článku', $array['description']);
        $this->assertSame('Text článku', $array['articleBody']);
    }

    public function testProvideOmitsDescriptionAndBodyWhenBlank(): void
    {
        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG, description: '   ', html: null);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/blog/x');

        $array = $this->provider->provide($category)->toArray();

        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayNotHasKey('articleBody', $array);
    }

    public function testProvideSetsImageFromUploadPrefixedWithAbsoluteUrl(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->method('getPath')->willReturn('/uploads/images/article.jpg');

        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG, upload: $upload);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/blog/x');

        $array = $this->provider->provide($category)->toArray();

        $this->assertSame('https://example.com/uploads/images/article.jpg', $array['image']);
    }

    public function testProvideOmitsImageWhenNoUpload(): void
    {
        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG, upload: null);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/blog/x');

        $array = $this->provider->provide($category)->toArray();

        $this->assertArrayNotHasKey('image', $array);
    }

    public function testProvidePrefersPublishedAtOverCreatedAt(): void
    {
        $published = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $created = new \DateTimeImmutable('2026-04-01T10:00:00+00:00');

        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG, publishedAt: $published, createdAt: $created);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/blog/x');

        $array = $this->provider->provide($category)->toArray();

        $this->assertSame($published->format(\DateTimeInterface::ATOM), $array['datePublished']);
    }

    public function testProvideFallsBackToCreatedAtWhenNoPublishedAt(): void
    {
        $created = new \DateTimeImmutable('2026-04-01T10:00:00+00:00');
        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG, publishedAt: null, createdAt: $created);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/blog/x');

        $array = $this->provider->provide($category)->toArray();

        $this->assertSame($created->format(\DateTimeInterface::ATOM), $array['datePublished']);
    }

    public function testProvideOmitsDatePublishedWhenBothDatesAreNull(): void
    {
        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG, publishedAt: null, createdAt: null);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/blog/x');

        $array = $this->provider->provide($category)->toArray();

        $this->assertArrayNotHasKey('datePublished', $array);
    }

    public function testProvideSetsKeywordsFromLabels(): void
    {
        $labelA = $this->createMock(Label::class);
        $labelA->method('getName')->willReturn('Lepení');
        $labelB = $this->createMock(Label::class);
        $labelB->method('getName')->willReturn('Kovy');

        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG, labels: [$labelA, $labelB]);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/blog/x');

        $array = $this->provider->provide($category)->toArray();

        $this->assertSame(['Lepení', 'Kovy'], $array['keywords']);
    }

    public function testProvideSetsAuthorFromFirstPerson(): void
    {
        $person = $this->createMock(Person::class);
        $person->method('getFullName')->willReturn('Jan Novák');

        $categoryPerson = $this->createMock(CategoryPerson::class);
        $categoryPerson->method('getPerson')->willReturn($person);

        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG, persons: [$categoryPerson]);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/blog/x');

        $array = $this->provider->provide($category)->toArray();

        $this->assertSame('Jan Novák', $array['author']['name']);
    }

    public function testProvideOmitsAuthorWhenNoPersons(): void
    {
        $category = $this->createCategoryStub(CategoryTypeEnum::BLOG, persons: []);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/blog/x');

        $array = $this->provider->provide($category)->toArray();

        $this->assertArrayNotHasKey('author', $array);
    }

    public function testGetPriorityReturnsZero(): void
    {
        $this->assertSame(0, $this->provider->getPriority());
    }

    private function createCategoryStub(
        CategoryTypeEnum   $categoryType,
        string              $slug = 'article-slug',
        string              $name = 'Article',
        ?string             $title = null,
        ?string             $description = null,
        ?string             $html = null,
        ?Upload             $upload = null,
        ?\DateTimeImmutable $publishedAt = null,
        ?\DateTimeImmutable $createdAt = null,
        array               $labels = [],
        array               $persons = [],
    ): CategoryEntity&MockObject {
        $categoryTypeMock = $this->createMock(CategoryType::class);
        $categoryTypeMock->method('getId')->willReturn($categoryType->value);

        $category = $this->createMock(CategoryEntity::class);
        $category->method('getCategoryType')->willReturn($categoryTypeMock);
        $category->method('getSlug')->willReturn($slug);
        $category->method('getName')->willReturn($name);
        $category->method('getTitle')->willReturn($title);
        $category->method('getDescription')->willReturn($description);
        $category->method('getHtml')->willReturn($html);
        $category->method('getUpload')->willReturn($upload);
        $category->method('getPublishedAt')->willReturn($publishedAt);
        $category->method('getCreatedAt')->willReturn($createdAt);
        $category->method('getLabels')->willReturn(new ArrayCollection($labels));
        $category->method('getPersons')->willReturn(new ArrayCollection($persons));

        return $category;
    }
}
