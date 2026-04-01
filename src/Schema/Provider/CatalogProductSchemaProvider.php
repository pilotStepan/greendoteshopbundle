<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Product;
use Greendot\EshopBundle\Enum\ProductViewTypeEnum;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;


class CatalogProductSchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly ProductSchemaBuilder $builder,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof ProductEntity
            && $object->getProductViewType()?->getId() === ProductViewTypeEnum::CATALOGUE->value;
    }

    public function provide(mixed $object): Product
    {
        if (!$object instanceof ProductEntity) {
            throw new UnsupportedSchemaSubjectException();
        }

        return $this->builder
            ->forProduct($object)
            ->build()
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
