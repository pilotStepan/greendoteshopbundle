<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Product as ProductSchema;
use Greendot\EshopBundle\Enum\ProductViewTypeEnum;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;

class EshopProductSchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly ProductSchemaBuilder $builder,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof ProductEntity
            && $object->getProductViewType()?->getId() === ProductViewTypeEnum::ESHOP->value
            && $object->getProductVariants()->count() === 1;
    }

    public function provide(mixed $object): ProductSchema
    {
        if (!$this->supports($object)) {
            throw new UnsupportedSchemaSubjectException();
        }

        /** @var ProductEntity $object */
        $variant = $object->getProductVariants()->first();

        return $this->builder
            ->forProductWithVariant($object, $variant)
            ->withAggregateRating()
            ->withReviews()
            ->withProductRelationships()
            ->build()
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }
}