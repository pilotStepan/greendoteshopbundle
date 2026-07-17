<?php

namespace Greendot\EshopBundle\Schema\Builder;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Product as ProductSchema;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Repository\Project\ProductProductRepository;
use Greendot\EshopBundle\Entity\Project\ProductVariant as ProductVariantEntity;

class ProductSchemaBuilder
{
    public const CONDITION_NEW = 'https://schema.org/NewCondition';

    public const SCHEMA_VARIES_BY_MAP = [
        'velikost' => 'size',
        'barva' => 'color',
        'váha' => 'weight',
        'šířka' => 'width',
    ];

    private ?ProductEntity $product = null;
    private ?ProductVariantEntity $variant = null;
    private ?ProductSchema $schema = null;

    public function __construct(
        private readonly UrlGeneratorInterface      $urlGenerator,
        #[Autowire(param: 'greendot_eshop.global.absolute_url')]
        private readonly string                     $absoluteUrl,
        private readonly ReviewRepository           $reviewRepository,
        private readonly ProductRepository          $productRepository,
        private readonly ProductProductRepository   $productProductRepository,
        private readonly CurrencyManager            $currencyManager,
        private readonly ProductVariantPriceFactory $priceFactory,
    ) {}

    public function __clone(): void
    {
        if ($this->schema !== null) {
            $this->schema = clone $this->schema;
        }
    }

    public function forProduct(ProductEntity $product): self
    {
        $clone = clone $this;
        $clone->product = $product;

        $url = $this->urlGenerator->generate($product->getControllerName(), [
            'slug' => $product->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $clone->schema = Schema::product()
            ->identifier(sprintf('%s#product', $url))
            ->url($url)
            ->name($product->getName())
            ->image($this->absoluteUrl . $product->getUpload()?->getPath())
            ->brand(Schema::brand()
                ->name($product->getProducer()?->getName()),
            )
            ->description($product->getTitle())
        ;

        return $clone;
    }

    public function forProductWithVariant(ProductEntity $product, ProductVariantEntity $variant): self
    {
        return $this->forProduct($product)->forProductVariant($variant);
    }

    private function forProductVariant(ProductVariantEntity $variant): self
    {
        if ($this->product === null || $this->schema === null) {
            throw new \LogicException('Call forProduct() before forProductVariant().');
        }

        if ($variant->getProduct() === null) {
            throw new \LogicException('ProductVariant must be associated with a Product.');
        }

        $clone = clone $this;
        $clone->variant = $variant;

        $url = $this->urlGenerator->generate($variant->getProduct()->getControllerName(), [
            'slug' => $variant->getProduct()->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $clone->schema
            ->name($variant->getName() ?? $variant->getProduct()->getName())
            ->image($this->variantImageUrl($variant))
            ->offers(Schema::offer()
                ->url($url)
                ->price(
                    $this->priceFactory->create(
                        $variant,
                        $this->currencyManager->get(),
                        vatCalculationType: VatCalculationType::WithVAT,
                    )->getPrice(),
                )
                ->priceCurrency($this->getCurrencyCode())
                ->availability($this->availabilityUrl($variant))
                ->itemCondition(self::CONDITION_NEW),
            )
        ;

        return $clone;
    }

    public function buildVariantReference(ProductVariantEntity $variant): ProductSchema
    {
        if ($variant->getProduct() === null) {
            throw new \LogicException('ProductVariant must be associated with a Product.');
        }

        $product = $variant->getProduct();
        $url = $this->urlGenerator->generate($product->getControllerName(), [
            'slug' => $product->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $schema = Schema::product()
            ->identifier(sprintf('%s#variant-%d', $url, $variant->getId() ?? 0))
            ->url($url)
            ->name($variant->getName() ?? $product->getName())
            ->image($this->variantImageUrl($variant))
            ->description($variant->getName() ?? $product->getTitle() ?? $product->getName())
            ->brand(Schema::brand()->name($product->getProducer()?->getName()))
            ->offers(Schema::offer()
                ->url($url)
                ->price(
                    $this->priceFactory->create(
                        $variant,
                        $this->currencyManager->get(),
                        vatCalculationType: VatCalculationType::WithVAT,
                    )->getPrice(),
                )
                ->priceCurrency($this->getCurrencyCode())
                ->availability($this->availabilityUrl($variant))
                ->itemCondition(self::CONDITION_NEW),
            )
        ;

        $this->applyVariantProperties($schema, $variant);

        return $schema;
    }

    private function variantImageUrl(ProductVariantEntity $variant): ?string
    {
        $path = $variant->getUpload()?->getPath() ?? $variant->getProduct()?->getUpload()?->getPath();

        return $path ? $this->absoluteUrl . $path : null;
    }

    private function availabilityUrl(ProductVariantEntity $variant): string
    {
        return $variant->getAvailability()?->getIsPurchasable()
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';
    }

    private function applyVariantProperties(ProductSchema $schema, ProductVariantEntity $variant): void
    {
        foreach ($variant->getParameters() as $parameter) {
            $groupName = $parameter->getParameterGroup()?->getName();
            if ($groupName === null) {
                continue;
            }

            $property = self::SCHEMA_VARIES_BY_MAP[mb_strtolower($groupName)] ?? null;
            $value = $parameter->getColorName() ?? $parameter->getData();

            if ($property !== null && $value !== null && method_exists($schema, $property)) {
                $schema->{$property}($value);
            }
        }
    }

    protected function getCurrencyCode(): string
    {
        return $this->currencyManager->get()->getName();
    }

    public function withAggregateRating(): self
    {
        if ($this->product === null || $this->schema === null) {
            throw new \LogicException('Call forProduct() before withAggregateRating().');
        }

        $reviewCount = $this->reviewRepository->getReviewCountForProduct($this->product);
        if ($reviewCount <= 0) {
            return $this;
        }

        $clone = clone $this;
        $clone->schema->aggregateRating(Schema::aggregateRating()
            ->ratingValue($this->reviewRepository->getAvgRatingValueForProduct($this->product))
            ->reviewCount($reviewCount),
        );

        return $clone;
    }

    public function withReviews(int $limit = 10): self
    {
        if ($this->product === null || $this->schema === null) {
            throw new \LogicException('Call forProduct() before withReviews().');
        }

        $reviews = $this->productRepository->findApprovedReviews($this->product, $limit);

        if (empty($reviews)) {
            return $this;
        }

        $clone = clone $this;
        $clone->schema->reviews(array_map(
            fn($review) => Schema::review()
                ->author(Schema::person()
                    ->name($review->getReviewerName()),
                )
                ->reviewRating(Schema::rating()
                    ->ratingValue($review->getStars()),
                )
                ->reviewBody($review->getContents()),
            $reviews,
        ));

        return $clone;
    }

    public function withProductRelationships(): self
    {
        if ($this->product === null || $this->schema === null) {
            throw new \LogicException('Call forProduct() before withProductRelationships().');
        }

        $clone = clone $this;
        $this->applyRelationships($clone->schema, $this->product);

        return $clone;
    }

    public function applyRelationships(BaseType $schema, ProductEntity $product): void
    {
        $relations = $this->productProductRepository->findBy(['parentProduct' => $product]);

        $similarTo = [];
        $relatedTo = [];

        foreach ($relations as $relation) {
            $child = $relation->getChildrenProduct();
            if ($child === null) {
                continue;
            }
            $url = $this->urlGenerator->generate($child->getControllerName(), [
                'slug' => $child->getSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $ref = Schema::product()->url($url)->name($child->getName());

            match ($relation->getProductProductType()->getName()) {
                'RELATED'    => $similarTo[] = $ref,
                'COMPLEMENT' => $relatedTo[] = $ref,
                default      => null,
            };
        }

        if (!empty($similarTo)) {
            $schema->isSimilarTo($similarTo);
        }
        if (!empty($relatedTo)) {
            $schema->isRelatedTo($relatedTo);
        }
    }

    public function buildForListing(ProductEntity $product): ThingContract
    {
        $variants = $product->getProductVariants()->toArray();
        $variantCount = count($variants);

        if ($variantCount === 0) {
            return $this->forProduct($product)->build();
        }

        if ($variantCount === 1) {
            return $this->forProduct($product)->forProductVariant($variants[0])->withAggregateRating()->build();
        }

        $url = $this->urlGenerator->generate(
            'shop_product',
            ['slug' => $product->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $currency = $this->currencyManager->get();

        $prices = array_filter(array_map(
            fn($v) => $this->priceFactory->create($v, $currency, vatCalculationType: VatCalculationType::WithVAT)->getPrice(),
            $variants,
        ));

        $schema = Schema::productGroup()
            ->identifier(sprintf('%s#group', $url))
            ->url($url)
            ->name($product->getName())
            ->productGroupID((string) $product->getId())
            ->brand(Schema::brand()->name($product->getProducer()?->getName()))
            ->image($this->absoluteUrl . $product->getUpload()?->getPath())
            ->hasVariant(
                array_map(
                    fn($v) => $this->buildVariantReference($v),
                    $variants,
                ),
            )
        ;

        if (!empty($prices)) {
            $schema->offers(Schema::aggregateOffer()
                ->lowPrice(min($prices))
                ->highPrice(max($prices))
                ->priceCurrency($currency->getName())
                ->offerCount($variantCount),
            );
        }

        $reviewCount = $this->reviewRepository->getReviewCountForProduct($product);
        if ($reviewCount > 0) {
            $schema->aggregateRating(Schema::aggregateRating()
                ->ratingValue($this->reviewRepository->getAvgRatingValueForProduct($product))
                ->reviewCount($reviewCount),
            );
        }

        return $schema;
    }

    public function build(): ProductSchema
    {
        if ($this->schema === null) {
            throw new \LogicException('Call forProduct() before build().');
        }

        return clone $this->schema;
    }
}
