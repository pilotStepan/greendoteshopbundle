<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use App\Enum\ProductViewTypeEnum;
use Spatie\SchemaOrg\ProductGroup;
use Greendot\EshopBundle\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;

class EshopProductGroupSchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ProductRepository     $productRepository,
        private readonly ProductSchemaBuilder  $productSchemaBuilder,
        private readonly ReviewRepository      $reviewRepository,
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

        $url = $this->urlGenerator->generate(
            'shop_product',
            ['slug' => $object->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return Schema::productGroup()
            ->identifier(sprintf('%s#group', $url))
            ->name($object->getName())
            ->description($object->getDescription())
            ->brand(Schema::brand()->name($object->getProducer()?->getName()))
            ->variesBy(
                array_map(
                    fn($paramGroup) => $paramGroup->getName(),
                    $this->productRepository->findVariantParameterGroupsByProduct($object),
                ),
            )
            ->hasVariant(
                array_map(
                    fn($variant) => $this->productSchemaBuilder->forProductVariant($variant)->build(),
                    $object->getProductVariants()->toArray(),
                ),
            )
            ->aggregateRating(
                Schema::aggregateRating()
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
//            ->isRelatedTo()
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
