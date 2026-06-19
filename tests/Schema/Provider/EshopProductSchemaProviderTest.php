<?php

namespace Greendot\EshopBundle\Tests\Schema\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\SchemaOrg\Product as ProductSchema;
use Greendot\EshopBundle\Enum\ProductViewTypeEnum;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Schema\Provider\EshopProductSchemaProvider;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Entity\Project\ProductVariant as ProductVariantEntity;
use Greendot\EshopBundle\Entity\Project\ProductViewType;

class EshopProductSchemaProviderTest extends TestCase
{
    private ProductSchemaBuilder&MockObject $builder;
    private EshopProductSchemaProvider $provider;

    protected function setUp(): void
    {
        $this->builder = $this->createMock(ProductSchemaBuilder::class);
        $this->provider = new EshopProductSchemaProvider($this->builder);
    }

    public function testSupportsReturnsTrueForEshopWithOneVariant(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 1);
        $this->assertTrue($this->provider->supports($product));
    }

    public function testSupportsReturnsFalseForCatalogViewType(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::CATALOGUE, variantCount: 1);
        $this->assertFalse($this->provider->supports($product));
    }

    public function testSupportsReturnsFalseForEshopWithMultipleVariants(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 2);
        $this->assertFalse($this->provider->supports($product));
    }

    public function testSupportsReturnsFalseForEshopWithNoVariants(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 0);
        $this->assertFalse($this->provider->supports($product));
    }

    public function testSupportsReturnsFalseForNonProductObject(): void
    {
        $this->assertFalse($this->provider->supports(new \stdClass()));
    }

    public function testProvideReturnsProductSchemaViaBuilderChain(): void
    {
        $variant = $this->createMock(ProductVariantEntity::class);
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 1, firstVariant: $variant);
        $expectedSchema = new ProductSchema();

        $this->builder->expects($this->once())
            ->method('forProductWithVariant')
            ->with($product, $variant)
            ->willReturn($this->builder);

        $this->builder->expects($this->once())
            ->method('withAggregateRating')
            ->willReturn($this->builder);

        $this->builder->expects($this->once())
            ->method('withReviews')
            ->willReturn($this->builder);

        $this->builder->expects($this->once())
            ->method('withProductRelationships')
            ->willReturn($this->builder);

        $this->builder->expects($this->once())
            ->method('build')
            ->willReturn($expectedSchema);

        $result = $this->provider->provide($product);
        $this->assertSame($expectedSchema, $result);
    }

    private function createProductMock(
        ProductViewTypeEnum $viewType,
        int $variantCount,
        ?ProductVariantEntity $firstVariant = null,
    ): ProductEntity&MockObject {
        $viewTypeMock = $this->createMock(ProductViewType::class);
        $viewTypeMock->method('getId')->willReturn($viewType->value);

        $variants = [];
        for ($i = 0; $i < $variantCount; $i++) {
            $variants[] = $firstVariant ?? $this->createMock(ProductVariantEntity::class);
        }
        $collection = new ArrayCollection($variants);

        $product = $this->createMock(ProductEntity::class);
        $product->method('getProductViewType')->willReturn($viewTypeMock);
        $product->method('getProductVariants')->willReturn($collection);

        return $product;
    }
}
