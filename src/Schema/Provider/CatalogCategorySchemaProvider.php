<?php

namespace Greendot\EshopBundle\Schema\Provider;

use App\Enum\CategoryType;
use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\ItemList;
use Spatie\SchemaOrg\ListItem;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;


class CatalogCategorySchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly ProductRepository    $productRepository,
        private readonly ProductSchemaBuilder $productSchemaBuilder,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof CategoryEntity
            && $object->getCategoryType()->getId() === CategoryType::Catalog->value;
    }

    public function provide(mixed $object): ItemList
    {
        if (!$object instanceof CategoryEntity) {
            throw new UnsupportedSchemaSubjectException();
        }

        $products = $this->productRepository->findCategoryProducts($object, 20); // TODO: pagination?

        $elements = array_map(
            fn($p, $index) => $this->mapToListItem($p, $index + 1),
            $products,
            array_keys($products),
        );

        return Schema::itemList()
            ->name($object->getName())
            ->itemListElement($elements)
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }

    private function mapToListItem(ProductEntity $product, int $position): ListItem
    {
        return Schema::listItem()
            ->position($position)
            ->item($this->productSchemaBuilder
                ->forProduct($product)
                ->build(),
            )
        ;
    }
}
