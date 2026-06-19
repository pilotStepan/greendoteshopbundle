<?php

namespace Greendot\EshopBundle\Tests\Schema\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\SchemaOrg\Product as ProductSchema;
use Greendot\EshopBundle\Enum\ProductViewTypeEnum;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Schema\Provider\CatalogProductSchemaProvider;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Entity\Project\ProductViewType;

class CatalogProductSchemaProviderTest extends TestCase
{
    private ProductSchemaBuilder&MockObject $builder;
    private CatalogProductSchemaProvider $provider;

    protected function setUp(): void
    {
        $this->builder = $this->createMock(ProductSchemaBuilder::class);
        $this->provider = new CatalogProductSchemaProvider($this->builder);
    }

    public function testSupportsReturnsTrueForCatalogProduct(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::CATALOGUE);
        $this->assertTrue($this->provider->supports($product));
    }

    public function testSupportsReturnsFalseForEshopViewType(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP);
        $this->assertFalse($this->provider->supports($product));
    }

    public function testSupportsReturnsFalseForNonProductObject(): void
    {
        $this->assertFalse($this->provider->supports(new \stdClass()));
        $this->assertFalse($this->provider->supports(null));
    }

    public function testProvideReturnsProductSchemaFromBuilder(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::CATALOGUE);
        $schema = new ProductSchema();

        $this->builder->expects($this->once())
            ->method('forProduct')
            ->with($product)
            ->willReturn($this->builder);

        $this->builder->expects($this->once())
            ->method('build')
            ->willReturn($schema);

        $result = $this->provider->provide($product);
        $this->assertSame($schema, $result);
    }

    private function createProductMock(ProductViewTypeEnum $viewType): ProductEntity&MockObject
    {
        $viewTypeMock = $this->createMock(ProductViewType::class);
        $viewTypeMock->method('getId')->willReturn($viewType->value);

        $product = $this->createMock(ProductEntity::class);
        $product->method('getProductViewType')->willReturn($viewTypeMock);
        $product->method('getProductVariants')->willReturn(new ArrayCollection());

        return $product;
    }
}
