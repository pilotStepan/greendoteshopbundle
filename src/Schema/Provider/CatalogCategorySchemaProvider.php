<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\ItemList;
use Spatie\SchemaOrg\ListItem;
use Greendot\EshopBundle\Enum\CategoryTypeEnum;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Schema\Builder\ProductSchemaBuilder;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Schema\Context\ItemListSchemaContext;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;


class CatalogCategorySchemaProvider implements SchemaProviderInterface
{
    private const SUPPORTED_TYPES = [
        CategoryTypeEnum::CATEGORY->value,
        CategoryTypeEnum::SUB_CATEGORY->value,
    ];

    public function __construct(
        private readonly ProductRepository    $productRepository,
        private readonly ProductSchemaBuilder $productSchemaBuilder,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof ItemListSchemaContext
            && in_array($object->category->getCategoryType()->getId(), self::SUPPORTED_TYPES, true);
    }

    public function provide(mixed $object): ItemList
    {
        if (!$this->supports($object)) {
            throw new UnsupportedSchemaSubjectException();
        }

        /** @var ItemListSchemaContext $object */

        $category = $object->category;
        $offset = $object->getOffset();
        $limit = $object->itemsPerPage;

        $products = $this->productRepository->findCategoryProductsOrdered($category, $limit, $offset);

        $elements = array_map(
            fn($p, $index) => $this->mapToListItem($p, $offset + $index + 1),
            $products,
            array_keys($products),
        );

        return Schema::itemList()
            ->name($category->getName())
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
            ->item($this->productSchemaBuilder->buildForListing($product))
        ;
    }
}
