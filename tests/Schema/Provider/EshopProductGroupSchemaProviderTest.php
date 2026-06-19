<?php

namespace Greendot\EshopBundle\Tests\Schema\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\SchemaOrg\Product as ProductSchema;
use Greendot\EshopBundle\Enum\ProductViewTypeEnum;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Schema\Provider\EshopProductGroupSchemaProvider;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Entity\Project\ProductVariant as ProductVariantEntity;
use Greendot\EshopBundle\Entity\Project\ProductViewType;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EshopProductGroupSchemaProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private ProductRepository&MockObject $productRepository;
    private ReviewRepository&MockObject $reviewRepository;
    private ProductSchemaBuilder&MockObject $builder;
    private ProductVariantPriceFactory&MockObject $priceFactory;
    private CurrencyManager&MockObject $currencyManager;
    private EshopProductGroupSchemaProvider $provider;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->builder = $this->createMock(ProductSchemaBuilder::class);
        $this->priceFactory = $this->createMock(ProductVariantPriceFactory::class);
        $this->currencyManager = $this->createMock(CurrencyManager::class);

        $this->provider = new EshopProductGroupSchemaProvider(
            $this->urlGenerator,
            $this->productRepository,
            $this->reviewRepository,
            $this->builder,
            $this->priceFactory,
            $this->currencyManager,
        );
    }

    public function testSupportsReturnsTrueForEshopWithMultipleVariants(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 2);
        $this->assertTrue($this->provider->supports($product));
    }

    public function testSupportsReturnsFalseForEshopWithOneVariant(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 1);
        $this->assertFalse($this->provider->supports($product));
    }

    public function testSupportsReturnsFalseForCatalogType(): void
    {
        $product = $this->createProductMock(ProductViewTypeEnum::CATALOGUE, variantCount: 2);
        $this->assertFalse($this->provider->supports($product));
    }

    public function testSupportsReturnsFalseForNonProductObject(): void
    {
        $this->assertFalse($this->provider->supports(new \stdClass()));
    }

    public function testProvideSetsPriceRangeOnAggregateOffer(): void
    {
        $variantA = $this->createMock(ProductVariantEntity::class);
        $variantB = $this->createMock(ProductVariantEntity::class);
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 2, variants: [$variantA, $variantB]);

        $currency = $this->createMock(Currency::class);
        $currency->method('getName')->willReturn('CZK');
        $this->currencyManager->method('get')->willReturn($currency);

        $priceA = $this->createMock(ProductVariantPrice::class);
        $priceA->method('getPrice')->willReturn(10.0);
        $priceB = $this->createMock(ProductVariantPrice::class);
        $priceB->method('getPrice')->willReturn(30.0);

        $this->priceFactory->method('create')
            ->willReturnOnConsecutiveCalls($priceA, $priceB);

        $this->productRepository->method('findVariantParameterGroupsByProduct')->willReturn([]);
        $this->productRepository->method('findApprovedReviews')->willReturn([]);
        $this->reviewRepository->method('getAvgRatingValueForProduct')->willReturn(0.0);
        $this->reviewRepository->method('getReviewCountForProduct')->willReturn(0);

        $this->builder->method('buildVariantReference')->willReturn(new ProductSchema());
        $this->builder->method('applyRelationships');

        $this->urlGenerator->method('generate')->willReturn('https://example.com/product');

        $product->method('getName')->willReturn('Test Product');
        $product->method('getDescription')->willReturn('Description');
        $product->method('getSlug')->willReturn('test-product');
        $product->method('getControllerName')->willReturn('product_show');
        $product->method('getProducer')->willReturn(null);

        $schema = $this->provider->provide($product);
        $array = $schema->toArray();

        $offer = $array['offers'];
        $this->assertSame(10.0, $offer['lowPrice']);
        $this->assertSame(30.0, $offer['highPrice']);
        $this->assertSame('CZK', $offer['priceCurrency']);
        $this->assertSame(2, $offer['offerCount']);
    }

    private function createProductMock(
        ProductViewTypeEnum $viewType,
        int $variantCount,
        array $variants = [],
    ): ProductEntity&MockObject {
        $viewTypeMock = $this->createMock(ProductViewType::class);
        $viewTypeMock->method('getId')->willReturn($viewType->value);

        if (empty($variants)) {
            for ($i = 0; $i < $variantCount; $i++) {
                $variants[] = $this->createMock(ProductVariantEntity::class);
            }
        }
        $collection = new ArrayCollection($variants);

        $product = $this->createMock(ProductEntity::class);
        $product->method('getProductViewType')->willReturn($viewTypeMock);
        $product->method('getProductVariants')->willReturn($collection);

        return $product;
    }
}
