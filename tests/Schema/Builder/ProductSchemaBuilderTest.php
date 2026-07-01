<?php

namespace Greendot\EshopBundle\Tests\Schema\Builder;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Repository\Project\ProductProductRepository;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Entity\Project\ProductVariant as ProductVariantEntity;
use Greendot\EshopBundle\Entity\Project\Availability;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Greendot\EshopBundle\Entity\Project\Review;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProductSchemaBuilderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private ReviewRepository&MockObject $reviewRepository;
    private ProductRepository&MockObject $productRepository;
    private ProductProductRepository&MockObject $productProductRepository;
    private CurrencyManager&MockObject $currencyManager;
    private ProductVariantPriceFactory&MockObject $priceFactory;
    private ProductSchemaBuilder $builder;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/product-p');

        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->productProductRepository = $this->createMock(ProductProductRepository::class);

        $this->currencyManager = $this->createMock(CurrencyManager::class);
        $currency = $this->createMock(Currency::class);
        $currency->method('getName')->willReturn('CZK');
        $this->currencyManager->method('get')->willReturn($currency);

        $this->priceFactory = $this->createMock(ProductVariantPriceFactory::class);
        $price = $this->createMock(ProductVariantPrice::class);
        $price->method('getPrice')->willReturn(100.0);
        $this->priceFactory->method('create')->willReturn($price);

        $this->builder = new ProductSchemaBuilder(
            $this->urlGenerator,
            'https://example.com',
            $this->reviewRepository,
            $this->productRepository,
            $this->productProductRepository,
            $this->currencyManager,
            $this->priceFactory,
        );
    }

    private function createProductMock(): ProductEntity&MockObject
    {
        $product = $this->createMock(ProductEntity::class);
        $product->method('getName')->willReturn('Test Product');
        $product->method('getTitle')->willReturn('Test Product Title');
        $product->method('getSlug')->willReturn('test-product-p');
        $product->method('getControllerName')->willReturn('shop_product');

        return $product;
    }

    private function createVariantMock(ProductEntity $product, bool $purchasable = true, array $parameters = []): ProductVariantEntity&MockObject
    {
        $availability = $this->createMock(Availability::class);
        $availability->method('getIsPurchasable')->willReturn($purchasable);

        $variant = $this->createMock(ProductVariantEntity::class);
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getAvailability')->willReturn($availability);
        $variant->method('getParameters')->willReturn(new ArrayCollection($parameters));

        return $variant;
    }

    public function testAvailabilityAndConditionAreSerializedAsPlainEnumStrings(): void
    {
        $product = $this->createProductMock();
        $variant = $this->createVariantMock($product, purchasable: true);

        $schema = $this->builder->forProductWithVariant($product, $variant)->build();
        $offer = $schema->toArray()['offers'];

        // Google's Rich Results Test rejects these as nested {"@id": ...} objects;
        // they must be the bare enumeration URL string.
        $this->assertSame('https://schema.org/InStock', $offer['availability']);
        $this->assertSame('https://schema.org/NewCondition', $offer['itemCondition']);
    }

    public function testOutOfStockAvailability(): void
    {
        $product = $this->createProductMock();
        $variant = $this->createVariantMock($product, purchasable: false);

        $schema = $this->builder->forProductWithVariant($product, $variant)->build();
        $offer = $schema->toArray()['offers'];

        $this->assertSame('https://schema.org/OutOfStock', $offer['availability']);
    }

    public function testBuildVariantReferenceUsesPlainEnumStrings(): void
    {
        $product = $this->createProductMock();
        $variant = $this->createVariantMock($product, purchasable: true);

        $schema = $this->builder->buildVariantReference($variant);
        $offer = $schema->toArray()['offers'];

        $this->assertSame('https://schema.org/InStock', $offer['availability']);
        $this->assertSame('https://schema.org/NewCondition', $offer['itemCondition']);
    }

    public function testBuildVariantReferenceIncludesImageAndDescription(): void
    {
        $product = $this->createProductMock();
        $product->method('getName')->willReturn('Opasek Yogacentrum');
        $variant = $this->createVariantMock($product, purchasable: true);
        $variant->method('getName')->willReturn('Opasek Yogacentrum 300cm');

        $variantUpload = $this->createMock(\Greendot\EshopBundle\Entity\Project\Upload::class);
        $variantUpload->method('getPath')->willReturn('/uploads/variant.jpg');
        $variant->method('getUpload')->willReturn($variantUpload);

        $schema = $this->builder->buildVariantReference($variant)->toArray();

        $this->assertSame('Opasek Yogacentrum 300cm', $schema['description']);
        $this->assertNotEmpty($schema['image']);
    }

    public function testBuildVariantReferenceFallsBackToProductImageWhenVariantHasNoImage(): void
    {
        $product = $this->createProductMock();
        $productUpload = $this->createMock(\Greendot\EshopBundle\Entity\Project\Upload::class);
        $productUpload->method('getPath')->willReturn('/uploads/product.jpg');
        $product->method('getUpload')->willReturn($productUpload);

        $variant = $this->createVariantMock($product, purchasable: true);
        $variant->method('getUpload')->willReturn(null);

        $schema = $this->builder->buildVariantReference($variant)->toArray();

        $this->assertSame('https://example.com/uploads/product.jpg', $schema['image']);
    }

    public function testBuildVariantReferenceAppliesColorParameterAsDistinguishingProperty(): void
    {
        $product = $this->createProductMock();

        $group = $this->createMock(ParameterGroup::class);
        $group->method('getName')->willReturn('Barva');

        $parameter = $this->createMock(Parameter::class);
        $parameter->method('getParameterGroup')->willReturn($group);
        $parameter->method('getColorName')->willReturn('Red');
        $parameter->method('getData')->willReturn('#FF0000');

        $variant = $this->createVariantMock($product, purchasable: true, parameters: [$parameter]);

        $schema = $this->builder->buildVariantReference($variant)->toArray();

        $this->assertSame('Red', $schema['color']);
    }

    public function testWithAggregateRatingOmitsRatingWhenNoReviews(): void
    {
        $product = $this->createProductMock();
        $this->reviewRepository->method('getReviewCountForProduct')->willReturn(0);

        $schema = $this->builder->forProduct($product)->withAggregateRating()->build()->toArray();

        $this->assertArrayNotHasKey('aggregateRating', $schema);
    }

    public function testWithAggregateRatingIncludesRatingWhenReviewsExist(): void
    {
        $product = $this->createProductMock();
        $this->reviewRepository->method('getReviewCountForProduct')->willReturn(5);
        $this->reviewRepository->method('getAvgRatingValueForProduct')->willReturn(4.2);

        $schema = $this->builder->forProduct($product)->withAggregateRating()->build()->toArray();

        $this->assertSame(5, $schema['aggregateRating']['reviewCount']);
        $this->assertSame(4.2, $schema['aggregateRating']['ratingValue']);
    }

    public function testBuildForListingIncludesHasVariantWithOffersForMultiVariantProduct(): void
    {
        $product = $this->createProductMock();
        $variantA = $this->createVariantMock($product, purchasable: true);
        $variantB = $this->createVariantMock($product, purchasable: true);

        $collection = new ArrayCollection([$variantA, $variantB]);
        $product->method('getProductVariants')->willReturn($collection);
        $this->reviewRepository->method('getReviewCountForProduct')->willReturn(0);

        $schema = $this->builder->buildForListing($product)->toArray();

        // Google's Product-snippet validator requires ProductGroup to expose at least
        // one of hasVariant.offers, review, or aggregateRating; a top-level AggregateOffer
        // alone does not satisfy it.
        $this->assertCount(2, $schema['hasVariant']);
        $this->assertSame('https://schema.org/InStock', $schema['hasVariant'][0]['offers']['availability']);
        $this->assertArrayHasKey('offers', $schema['hasVariant'][0]);
    }

    public function testWithReviewsDoesNotLeakReviewerEmail(): void
    {
        $product = $this->createProductMock();

        $review = $this->createMock(Review::class);
        $review->method('getReviewerName')->willReturn('Jana');
        $review->method('getReviewerEmail')->willReturn('jana@example.com');
        $review->method('getStars')->willReturn(5);
        $review->method('getContents')->willReturn('Great product!');

        $this->productRepository->method('findApprovedReviews')->willReturn([$review]);

        $schema = $this->builder->forProduct($product)->withReviews()->build()->toArray();

        $this->assertSame('Jana', $schema['reviews'][0]['author']['name']);
        $this->assertArrayNotHasKey('email', $schema['reviews'][0]['author']);
    }
}
