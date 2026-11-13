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

        // Prices come from the builder, so a tenant overriding variantPrice() changes them here too.
        $this->builder->method('variantPrice')->willReturnMap([
            [$variantA, 10.0],
            [$variantB, 30.0],
        ]);

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

        $this->assertArrayNotHasKey('aggregateRating', $array);
        $this->assertArrayNotHasKey('reviews', $array);
    }

    public function testProvideSetsProductGroupIdFallingBackToInternalId(): void
    {
        $variantA = $this->createMock(ProductVariantEntity::class);
        $variantB = $this->createMock(ProductVariantEntity::class);
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 2, variants: [$variantA, $variantB]);

        $currency = $this->createMock(Currency::class);
        $currency->method('getName')->willReturn('CZK');
        $this->currencyManager->method('get')->willReturn($currency);

        $price = $this->createMock(ProductVariantPrice::class);
        $price->method('getPrice')->willReturn(10.0);
        $this->priceFactory->method('create')->willReturn($price);

        $this->productRepository->method('findVariantParameterGroupsByProduct')->willReturn([]);
        $this->productRepository->method('findApprovedReviews')->willReturn([]);
        $this->reviewRepository->method('getReviewCountForProduct')->willReturn(0);

        $this->builder->method('buildVariantReference')->willReturn(new ProductSchema());
        $this->builder->method('applyRelationships');

        $this->urlGenerator->method('generate')->willReturn('https://example.com/product');

        $product->method('getName')->willReturn('Test Product');
        $product->method('getDescription')->willReturn('Description');
        $product->method('getSlug')->willReturn('test-product');
        $product->method('getControllerName')->willReturn('product_show');
        $product->method('getProducer')->willReturn(null);
        $product->method('getExternalId')->willReturn(null);
        $product->method('getId')->willReturn(42);

        $array = $this->provider->provide($product)->toArray();

        $this->assertSame('42', $array['productGroupID']);
    }

    public function testProvideIncludesAggregateRatingAndReviewsWhenReviewsExist(): void
    {
        $variantA = $this->createMock(ProductVariantEntity::class);
        $variantB = $this->createMock(ProductVariantEntity::class);
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 2, variants: [$variantA, $variantB]);

        $currency = $this->createMock(Currency::class);
        $currency->method('getName')->willReturn('CZK');
        $this->currencyManager->method('get')->willReturn($currency);

        $price = $this->createMock(ProductVariantPrice::class);
        $price->method('getPrice')->willReturn(10.0);
        $this->priceFactory->method('create')->willReturn($price);

        $this->productRepository->method('findVariantParameterGroupsByProduct')->willReturn([]);

        $review = $this->createMock(\Greendot\EshopBundle\Entity\Project\Review::class);
        $review->method('getReviewerName')->willReturn('Petr');
        $review->method('getReviewerEmail')->willReturn('petr@example.com');
        $review->method('getStars')->willReturn(4);
        $review->method('getContents')->willReturn('Good.');
        $this->productRepository->method('findApprovedReviews')->willReturn([$review]);

        $this->reviewRepository->method('getAvgRatingValueForProduct')->willReturn(4.0);
        $this->reviewRepository->method('getReviewCountForProduct')->willReturn(1);

        $this->builder->method('buildVariantReference')->willReturn(new ProductSchema());
        $this->builder->method('applyRelationships');

        $this->urlGenerator->method('generate')->willReturn('https://example.com/product');

        $product->method('getName')->willReturn('Test Product');
        $product->method('getDescription')->willReturn('Description');
        $product->method('getSlug')->willReturn('test-product');
        $product->method('getControllerName')->willReturn('product_show');
        $product->method('getProducer')->willReturn(null);

        $array = $this->provider->provide($product)->toArray();

        $this->assertSame(1, $array['aggregateRating']['reviewCount']);
        $this->assertSame(4.0, $array['aggregateRating']['ratingValue']);
        $this->assertSame('Petr', $array['reviews'][0]['author']['name']);
        $this->assertArrayNotHasKey('email', $array['reviews'][0]['author']);
    }

    public function testProvideOmitsVariesByWhenNoVariantParameterGroupsAndStripsHtml(): void
    {
        $product = $this->createProvidableProduct(name: 'Evapo-Rust<sup>®</sup>', description: '<p>Odrezovač&nbsp;rzi</p>');
        $this->productRepository->method('findVariantParameterGroupsByProduct')->willReturn([]);

        $array = $this->provider->provide($product)->toArray();

        $this->assertArrayNotHasKey('variesBy', $array);
        $this->assertSame('Evapo-Rust®', $array['name']);
        $this->assertSame('Odrezovač rzi', $array['description']);
    }

    public function testProvideMapsVariesByAndTakesImageFromBuilder(): void
    {
        $product = $this->createProvidableProduct();
        $group = $this->createMock(\Greendot\EshopBundle\Entity\Project\ParameterGroup::class);
        $group->method('getName')->willReturn('Barva');
        $this->productRepository->method('findVariantParameterGroupsByProduct')->willReturn([$group]);
        $this->builder->method('productImageUrl')->willReturn('https://example.com/uploads/a.jpg');

        $array = $this->provider->provide($product)->toArray();

        $this->assertSame(['color'], $array['variesBy']);
        $this->assertSame('https://example.com/uploads/a.jpg', $array['image']);
    }

    public function testProvideOmitsAggregateOfferWhenNoVariantHasPrice(): void
    {
        $product = $this->createProvidableProduct();
        $this->productRepository->method('findVariantParameterGroupsByProduct')->willReturn([]);
        $this->builder->method('variantPrice')->willReturn(0.0);

        $array = $this->provider->provide($product)->toArray();

        $this->assertArrayNotHasKey('offers', $array);
    }

    private function createProvidableProduct(string $name = 'Test Product', string $description = 'Description'): ProductEntity&MockObject
    {
        $product = $this->createProductMock(ProductViewTypeEnum::ESHOP, variantCount: 2);

        $currency = $this->createMock(Currency::class);
        $currency->method('getName')->willReturn('CZK');
        $this->currencyManager->method('get')->willReturn($currency);

        $this->productRepository->method('findApprovedReviews')->willReturn([]);
        $this->reviewRepository->method('getReviewCountForProduct')->willReturn(0);
        $this->builder->method('buildVariantReference')->willReturn(new ProductSchema());
        $this->urlGenerator->method('generate')->willReturn('https://example.com/product');

        $product->method('getName')->willReturn($name);
        $product->method('getDescription')->willReturn($description);
        $product->method('getSlug')->willReturn('test-product');
        $product->method('getControllerName')->willReturn('product_show');
        $product->method('getProducer')->willReturn(null);

        return $product;
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
