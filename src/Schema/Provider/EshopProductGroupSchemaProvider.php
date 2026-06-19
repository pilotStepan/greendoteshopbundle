<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\ProductGroup;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Enum\ProductViewTypeEnum;
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
    private const SCHEMA_VARIES_BY_MAP = [
        'velikost' => 'size',
        'barva' => 'color',
        'váha' => 'weight',
        'šířka' => 'width',
    ];

    public function __construct(
        private readonly UrlGeneratorInterface      $urlGenerator,
        private readonly ProductRepository          $productRepository,
        private readonly ReviewRepository           $reviewRepository,
        private readonly ProductSchemaBuilder       $builder,
        private readonly ProductVariantPriceFactory $priceFactory,
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
            fn($variant) => $this->priceFactory->create(
                $variant,
                $currency,
                vatCalculationType: VatCalculationType::WithVAT,
            )->getPrice(),
            $variants,
        ));

        $aggregateOffer = !empty($prices)
            ? Schema::aggregateOffer()
                ->lowPrice(min($prices))
                ->highPrice(max($prices))
                ->priceCurrency($currency->getName())
                ->offerCount(count($variants))
            : null;

        $schema = Schema::productGroup()
            ->identifier(sprintf('%s#group',
                $this->urlGenerator->generate($object->getControllerName(), ['slug' => $object->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ))
            ->name($object->getName())
            ->description($object->getDescription())
            ->brand(Schema::brand()
                ->name($object->getProducer()?->getName()),
            )
            ->variesBy(
                array_map(
                    fn($paramGroup) => self::SCHEMA_VARIES_BY_MAP[mb_strtolower($paramGroup->getName())] ?? $paramGroup->getName(),
                    $this->productRepository->findVariantParameterGroupsByProduct($object),
                ),
            )
            ->hasVariant(
                array_map(
                    fn($variant) => $this->builder->buildVariantReference($variant),
                    $variants,
                ),
            )
            ->offers($aggregateOffer)
            ->aggregateRating(Schema::aggregateRating()
                ->ratingValue($this->reviewRepository->getAvgRatingValueForProduct($object))
                ->reviewCount($this->reviewRepository->getReviewCountForProduct($object)),
            )
            ->reviews(
                array_map(
                    fn($review) => Schema::review()
                        ->author(
                            Schema::person()
                                ->name($review->getReviewerName())
                                ->email($review->getReviewerEmail()),
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

        $this->builder->applyRelationships($schema, $object);

        return $schema;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
