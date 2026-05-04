<?php

namespace Greendot\EshopBundle\Schema\Builder;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\Product as ProductSchema;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Entity\Project\ProductVariant as ProductVariantEntity;

final class ProductSchemaBuilder
{
    private ?ProductEntity $product = null;
    private ?ProductVariantEntity $variant = null;
    private ?ProductSchema $schema = null;

    public function __construct(
        private readonly UrlGeneratorInterface      $urlGenerator,
        #[Autowire(param: 'greendot_eshop.global.absolute_url')]
        private readonly string                     $absoluteUrl,
        private readonly ReviewRepository           $reviewRepository,
        private readonly CurrencyManager            $currencyManager,
        private readonly ProductVariantPriceFactory $priceFactory,
    ) {}

    public function forProduct(ProductEntity $product): self
    {
        $clone = clone $this;
        $clone->product = $product;

        $url = $this->urlGenerator->generate('shop_product', [
            'slug' => $product->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL,
        );

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

    public function forProductVariant(ProductVariantEntity $variant): self
    {
        $clone = clone $this;
        $clone->variant = $variant;

        if ($variant->getProduct() === null) {
            throw new \LogicException('ProductVariant must be associated with a Product.');
        }

        $clone->product ??= $variant->getProduct();

        if ($clone->schema === null) {
            $url = $this->urlGenerator->generate('shop_product', [
                'slug' => $variant->getProduct()->getSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $clone->schema = Schema::product()
                ->identifier(sprintf('%s#variant-%d', $url, $variant->getId() ?? 0))
                ->url($url)
                ->brand(Schema::brand()
                    ->name($variant->getProduct()->getProducer()?->getName()),
                )
            ;
        }

        $clone->schema
            ->name($variant->getName() ?? $variant->getProduct()->getName())
            ->image($variant->getUpload()?->getPath() ? $this->absoluteUrl . $variant->getUpload()?->getPath() : null)
            ->offers(Schema::offer()
                ->price(
                    $this->priceFactory->create(
                        $variant,
                        $this->currencyManager->get(),
                        vatCalculationType: VatCalculationType::WithVAT,
                    )->getPrice(),
                )
                ->priceCurrency($this->currencyManager->get()->getSymbol()) // FIXME: should be code, not symbol
                ->availability(Schema::itemAvailability()
                    ->name($variant->getAvailability()?->getName()),
                ),
            )
        ;

        return $clone;
    }

    public function withAggregateRating(): self
    {
        if ($this->product === null || $this->schema === null) {
            throw new \LogicException('Call forProduct() before withAggregateRating().');
        }

        $clone = clone $this;
        $clone->schema
            ->aggregateRating(Schema::aggregateRating()
                ->ratingValue($this->reviewRepository->getAvgRatingValueForProduct($this->product))
                ->reviewCount($this->reviewRepository->getReviewCountForProduct($this->product)),
            )
        ;

        return $clone;
    }

    public function build(): ProductSchema
    {
        if ($this->schema === null) {
            throw new \LogicException('Call forProduct() before build().');
        }

        return clone $this->schema;
    }
}
