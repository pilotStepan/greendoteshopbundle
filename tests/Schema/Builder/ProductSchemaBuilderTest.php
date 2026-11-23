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
    /** @var array<int, true> spl_object_id() of variants whose price resolves to 0 ("price on request") */
    private array $unpricedVariants = [];

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
        $this->priceFactory->method('create')->willReturnCallback(function (ProductVariantEntity $variant) {
            $price = $this->createMock(ProductVariantPrice::class);
            $price->method('getPrice')->willReturn(isset($this->unpricedVariants[spl_object_id($variant)]) ? 0.0 : 100.0);

            return $price;
        });

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

        $listing = $this->builder->buildForListing($product);
        $this->assertInstanceOf(\Spatie\SchemaOrg\BaseType::class, $listing);
        $schema = $listing->toArray();

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

    public function testVariantWithoutPriceHasNoOffer(): void
    {
        $product = $this->createProductMock();
        $variant = $this->createVariantMock($product);
        $this->unpricedVariants[spl_object_id($variant)] = true;

        $schema = $this->builder->forProductWithVariant($product, $variant)->build()->toArray();

        $this->assertArrayNotHasKey('offers', $schema);
    }

    public function testBuildVariantReferenceWithoutPriceHasNoOffer(): void
    {
        $product = $this->createProductMock();
        $variant = $this->createVariantMock($product);
        $this->unpricedVariants[spl_object_id($variant)] = true;

        $schema = $this->builder->buildVariantReference($variant)->toArray();

        $this->assertArrayNotHasKey('offers', $schema);
    }

    public function testBuildForListingCountsOnlyPricedVariantsInAggregateOffer(): void
    {
        $product = $this->createProductMock();
        $priced = $this->createVariantMock($product);
        $unpriced = $this->createVariantMock($product);
        $this->unpricedVariants[spl_object_id($unpriced)] = true;

        $product->method('getProductVariants')->willReturn(new ArrayCollection([$priced, $unpriced]));
        $this->reviewRepository->method('getReviewCountForProduct')->willReturn(0);

        $schema = $this->builder->buildForListing($product)->toArray();

        $this->assertSame(1, $schema['offers']['offerCount']);
        $this->assertArrayHasKey('offers', $schema['hasVariant'][0]);
        $this->assertArrayNotHasKey('offers', $schema['hasVariant'][1]);
    }

    public function testProductWithoutAnyImageHasNoImageKey(): void
    {
        $product = $this->createProductMock();
        $variant = $this->createVariantMock($product);
        $product->method('getProductVariants')->willReturn(new ArrayCollection([$variant]));

        $schema = $this->builder->forProductWithVariant($product, $variant)->build()->toArray();

        // Previously absolute_url . null produced the bare domain as the image.
        $this->assertArrayNotHasKey('image', $schema);
    }

    public function testProductImageFallsBackToFirstVariantImage(): void
    {
        $product = $this->createProductMock();
        $withoutImage = $this->createVariantMock($product);
        $withImage = $this->createVariantMock($product);
        $upload = $this->createMock(\Greendot\EshopBundle\Entity\Project\Upload::class);
        $upload->method('getPath')->willReturn('/uploads/variant.jpg');
        $withImage->method('getUpload')->willReturn($upload);
        $product->method('getProductVariants')->willReturn(new ArrayCollection([$withoutImage, $withImage]));

        $this->assertSame('https://example.com/uploads/variant.jpg', $this->builder->productImageUrl($product));
    }

    public function testNamesAreStrippedOfHtml(): void
    {
        $product = $this->createMock(ProductEntity::class);
        $product->method('getName')->willReturn('Evapo-Rust<sup>®</sup>');
        $product->method('getTitle')->willReturn('Evapo-Rust<sup>®</sup> &amp; more');
        $product->method('getSlug')->willReturn('evapo-rust-p');
        $product->method('getControllerName')->willReturn('shop_product');

        $schema = $this->builder->forProduct($product)->build()->toArray();

        $this->assertSame('Evapo-Rust®', $schema['name']);
        $this->assertSame('Evapo-Rust® & more', $schema['description']);
    }

    public function testSingleVariantProductGetsVariantProperties(): void
    {
        $product = $this->createProductMock();
        $group = $this->createMock(ParameterGroup::class);
        $group->method('getName')->willReturn('Barva');
        $parameter = $this->createMock(Parameter::class);
        $parameter->method('getParameterGroup')->willReturn($group);
        $parameter->method('getColorName')->willReturn('Red');
        $variant = $this->createVariantMock($product, parameters: [$parameter]);

        $schema = $this->builder->forProductWithVariant($product, $variant)->build()->toArray();

        $this->assertSame('Red', $schema['color']);
    }

    public function testTenantOverridesOfPriceAvailabilityAndNameReachTheMarkup(): void
    {
        $builder = new class(
            $this->urlGenerator,
            'https://example.com',
            $this->reviewRepository,
            $this->productRepository,
            $this->productProductRepository,
            $this->currencyManager,
            $this->priceFactory,
        ) extends ProductSchemaBuilder {
            public function variantPrice(ProductVariantEntity $variant): ?float
            {
                return 55.5;
            }

            protected function availabilityUrl(ProductVariantEntity $variant): string
            {
                return 'https://schema.org/BackOrder';
            }

            protected function variantName(ProductVariantEntity $variant): ?string
            {
                return 'Custom name';
            }
        };

        $product = $this->createProductMock();
        $variantA = $this->createVariantMock($product);
        $variantB = $this->createVariantMock($product);
        $product->method('getProductVariants')->willReturn(new ArrayCollection([$variantA, $variantB]));
        $this->reviewRepository->method('getReviewCountForProduct')->willReturn(0);

        $single = $builder->forProductWithVariant($product, $variantA)->build()->toArray();
        $this->assertSame(55.5, $single['offers']['price']);
        $this->assertSame('https://schema.org/BackOrder', $single['offers']['availability']);
        $this->assertSame('Custom name', $single['name']);

        $group = $builder->buildForListing($product)->toArray();
        $this->assertSame(55.5, $group['offers']['lowPrice']);
        $this->assertSame('Custom name', $group['hasVariant'][0]['name']);
        $this->assertSame('https://schema.org/BackOrder', $group['hasVariant'][1]['offers']['availability']);
    }

    public function testProductWithoutProducerHasNoBrand(): void
    {
        $product = $this->createProductMock();
        $product->method('getProducer')->willReturn(null);
        $product->method('getProductVariants')->willReturn(new ArrayCollection());

        $array = $this->builder->forProduct($product)->build()->toArray();

        $this->assertArrayNotHasKey('brand', $array);
        $this->assertNull($this->builder->brand($product));
    }

    public function testDescriptionComesFromTheProductDescriptionHook(): void
    {
        $product = $this->createProductMock();
        $product->method('getProductVariants')->willReturn(new ArrayCollection());

        $this->assertSame('Test Product Title', $this->builder->productDescription($product));
        $this->assertSame('Test Product Title', $this->builder->forProduct($product)->build()->toArray()['description']);
    }

    public function testVariesByMapsKnownGroupsToSchemaOrgUrlsAndDropsUnknownOnes(): void
    {
        $product = $this->createProductMock();
        $groups = [];
        foreach (['Barva', 'Velikost', 'Objem', 'barva'] as $name) {
            $group = $this->createMock(ParameterGroup::class);
            $group->method('getName')->willReturn($name);
            $groups[] = $group;
        }
        $this->productRepository->method('findVariantParameterGroupsByProduct')->willReturn($groups);

        $this->assertSame(['https://schema.org/color', 'https://schema.org/size'], $this->builder->variesBy($product));
    }

    public function testRelationshipsUseOnlyLoadableChildren(): void
    {
        $product = $this->createProductMock();
        $child = $this->createProductMock();
        $type = $this->createMock(\Greendot\EshopBundle\Entity\Project\ProductProductType::class);
        $type->method('getName')->willReturn('RELATED');
        $relation = $this->createMock(\Greendot\EshopBundle\Entity\Project\ProductProduct::class);
        $relation->method('getChildrenProduct')->willReturn($child);
        $relation->method('getProductProductType')->willReturn($type);

        // findBy() would also return relations to products hidden by Doctrine filters,
        // whose proxies throw EntityNotFoundException when touched.
        $this->productProductRepository->expects($this->never())->method('findBy');
        $this->productProductRepository->expects($this->once())
            ->method('findWithLoadableChildren')->with($product)->willReturn([$relation]);

        $schema = $this->builder->forProduct($product)->withProductRelationships()->build()->toArray();

        $this->assertSame('Test Product', $schema['isSimilarTo'][0]['name']);
    }
}
