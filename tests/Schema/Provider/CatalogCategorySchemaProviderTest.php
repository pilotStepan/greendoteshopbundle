<?php

namespace Greendot\EshopBundle\Tests\Schema\Provider;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\SchemaOrg\Product as ProductSchema;
use Greendot\EshopBundle\Enum\CategoryTypeEnum;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Schema\Provider\CatalogCategorySchemaProvider;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Schema\Context\ItemListSchemaContext;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Greendot\EshopBundle\Entity\Project\CategoryType;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;

class CatalogCategorySchemaProviderTest extends TestCase
{
    private ProductRepository&MockObject $productRepository;
    private ProductSchemaBuilder&MockObject $builder;
    private CatalogCategorySchemaProvider $provider;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->builder = $this->createMock(ProductSchemaBuilder::class);
        $this->provider = new CatalogCategorySchemaProvider($this->productRepository, $this->builder);
    }

    public function testSupportsReturnsTrueForCategoryType(): void
    {
        $ctx = $this->createContext(CategoryTypeEnum::CATEGORY);
        $this->assertTrue($this->provider->supports($ctx));
    }

    public function testSupportsReturnsTrueForSubCategoryType(): void
    {
        $ctx = $this->createContext(CategoryTypeEnum::SUB_CATEGORY);
        $this->assertTrue($this->provider->supports($ctx));
    }

    public function testSupportsReturnsFalseForUnsupportedCategoryType(): void
    {
        $ctx = $this->createContext(CategoryTypeEnum::BLOG);
        $this->assertFalse($this->provider->supports($ctx));
    }

    public function testSupportsReturnsFalseForNonItemListContext(): void
    {
        $this->assertFalse($this->provider->supports(new \stdClass()));
        $this->assertFalse($this->provider->supports(null));
    }

    public function testProvideBuildsItemListWithProductsViaForProductBuild(): void
    {
        $ctx = $this->createContext(CategoryTypeEnum::CATEGORY, categoryName: 'Phones', page: 1, itemsPerPage: 2);

        $productA = $this->createMock(ProductEntity::class);
        $productB = $this->createMock(ProductEntity::class);

        $this->productRepository->expects($this->once())
            ->method('findCategoryProductsOrdered')
            ->with($ctx->category, 2, 0)
            ->willReturn([$productA, $productB]);

        $schemaA = (new ProductSchema())->name('A');
        $schemaB = (new ProductSchema())->name('B');

        $this->builder->expects($this->exactly(2))
            ->method('buildForListing')
            ->willReturnOnConsecutiveCalls($schemaA, $schemaB);

        $result = $this->provider->provide($ctx);
        $array = $result->toArray();

        $this->assertSame('Phones', $array['name']);
        $this->assertCount(2, $array['itemListElement']);
        $this->assertSame(1, $array['itemListElement'][0]['position']);
        $this->assertSame(2, $array['itemListElement'][1]['position']);
    }

    public function testProvidePositionsAccountForOffset(): void
    {
        $ctx = $this->createContext(CategoryTypeEnum::CATEGORY, page: 2, itemsPerPage: 10);

        $product = $this->createMock(ProductEntity::class);
        $this->productRepository->method('findCategoryProductsOrdered')
            ->with($ctx->category, 10, 10)
            ->willReturn([$product]);

        $this->builder->method('buildForListing')->willReturn(new ProductSchema());

        $result = $this->provider->provide($ctx);
        $array = $result->toArray();

        // offset is 10, so first item on page 2 is position 11
        $this->assertSame(11, $array['itemListElement'][0]['position']);
    }

    public function testGetPriorityReturnsZero(): void
    {
        $this->assertSame(0, $this->provider->getPriority());
    }

    private function createContext(
        CategoryTypeEnum $categoryType,
        string $categoryName = 'Category',
        int $page = 1,
        int $itemsPerPage = 30,
    ): ItemListSchemaContext {
        $categoryTypeMock = $this->createMock(CategoryType::class);
        $categoryTypeMock->method('getId')->willReturn($categoryType->value);

        $category = $this->createMock(CategoryEntity::class);
        $category->method('getCategoryType')->willReturn($categoryTypeMock);
        $category->method('getName')->willReturn($categoryName);

        return new ItemListSchemaContext($category, page: $page, itemsPerPage: $itemsPerPage);
    }
}
