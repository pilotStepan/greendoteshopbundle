<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\ProductGroup;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Enum\ProductViewTypeEnum;
use Greendot\EshopBundle\Schema\SchemaText;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;

class EshopProductGroupSchemaProvider implements SchemaProviderInterface
{
    /**
     * $priceFactory is unused (prices come from ProductSchemaBuilder::variantPrice()), but tenants
     * call this constructor positionally from their subclasses, so the signature stays.
     */
    public function __construct(
        private readonly UrlGeneratorInterface      $urlGenerator,
        private readonly ProductRepository          $productRepository,
        private readonly ReviewRepository           $reviewRepository,
        private readonly ProductSchemaBuilder       $builder,
        ProductVariantPriceFactory                  $priceFactory,
        private readonly CurrencyManager            $currencyManager,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof ProductEntity
            && $object->getProductViewType()?->getId() === ProductViewTypeEnum::ESHOP->value
            && $object->getProductVariants()->count() > 1;
    }

    public function provide(mixed $object): ProductGroup
    {
        if (!$this->supports($object)) {
            throw new UnsupportedSchemaSubjectException();
        }

        /** @var ProductEntity $object */
        $variants = $object->getProductVariants()->toArray();
        $currency = $this->currencyManager->get();

        $prices = array_filter(array_map(
            fn($variant) => $this->builder->variantPrice($variant),
            $variants,
        ));

        $aggregateOffer = !empty($prices)
            ? Schema::aggregateOffer()
                ->lowPrice(min($prices))
                ->highPrice(max($prices))
                ->priceCurrency($currency->getName())
                ->offerCount(count($prices))
            : null;

        $url = $this->urlGenerator->generate($object->getControllerName(), ['slug' => $object->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        $schema = Schema::productGroup()
            ->identifier(sprintf('%s#group', $url))
            ->url($url)
            ->name(SchemaText::plain($object->getName()))
            ->description(SchemaText::plain($object->getDescription()))
            ->image($this->builder->productImageUrl($object))
            ->productGroupID($object->getExternalId() ?? (string) $object->getId())
            ->brand($this->builder->brand($object))
            ->hasVariant(
                array_map(
                    fn($variant) => $this->builder->buildVariantReference($variant),
                    $variants,
                ),
            )
            ->offers($aggregateOffer)
        ;

        $variesBy = $this->builder->variesBy($object);
        if ($variesBy !== []) {
            $schema->variesBy($variesBy);
        }

        $reviewCount = $this->reviewRepository->getReviewCountForProduct($object);
        if ($reviewCount > 0) {
            $schema
                ->aggregateRating(Schema::aggregateRating()
                    ->ratingValue($this->reviewRepository->getAvgRatingValueForProduct($object))
                    ->reviewCount($reviewCount),
                )
                ->reviews(
                    array_map(
                        fn($review) => Schema::review()
                            ->author(
                                Schema::person()
                                    ->name($review->getReviewerName()),
                            )
                            ->reviewRating(
                                Schema::rating()
                                    ->ratingValue($review->getStars()),
                            )
                            ->reviewBody($review->getContents()),
                        $this->productRepository->findApprovedReviews($object, 10),
                    ),
                )
            ;
        }

        $this->builder->applyRelationships($schema, $object);

        return $schema;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
