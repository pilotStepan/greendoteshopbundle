<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\ProductGroup;
use Greendot\EshopBundle\Enum\ProductViewTypeEnum;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;

class EshopProductGroupSchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ProductRepository     $productRepository,
        private readonly ReviewRepository      $reviewRepository,
        private readonly ProductSchemaBuilder  $builder,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof ProductEntity
            && $object->getProductViewType()?->getId() === ProductViewTypeEnum::ESHOP->value
            && $object->getProductVariants()->count() > 1;
    }

    public function provide(mixed $object): ProductGroup
    {
        if (!$object instanceof ProductEntity) {
            throw new UnsupportedSchemaSubjectException();
        }

        return Schema::productGroup()
            ->identifier(sprintf('%s#group',
                $this->urlGenerator->generate('shop_product', ['slug' => $object->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ))
            ->name($object->getName())
            ->description($object->getDescription())
            ->brand(Schema::brand()
                ->name($object->getProducer()?->getName()),
            )
            ->variesBy(
                array_map(
                    fn($paramGroup) => $paramGroup->getName(),
                    $this->productRepository->findVariantParameterGroupsByProduct($object),
                ),
            )
            ->hasVariant(
                array_map(
                    fn($variant) => $this->builder->forProductVariant($variant)->build(),
                    $object->getProductVariants()->toArray(),
                ),
            )
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
                    $this->productRepository->findApprovedReviews($object),
                ),
            )
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
