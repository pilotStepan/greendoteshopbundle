<?php

namespace Greendot\EshopBundle\Schema\Provider;

use App\Enum\ProductViewTypeEnum;
use Spatie\SchemaOrg\Product as ProductSchema;
use Greendot\EshopBundle\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
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
        if (!$object instanceof ProductEntity) {
            throw new UnsupportedSchemaSubjectException();
        }

        return $this->builder
            ->forProduct($object)
            ->withAggregateRating()
            ->build()
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }
}