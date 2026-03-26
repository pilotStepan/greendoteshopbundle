<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\ListItem;
use Spatie\SchemaOrg\ItemList;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Context\BreadcrumbSchemaContext;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;

/**
 * Provides structured data for breadcrumbs.
 */
class DefaultBreadcrumbSchemaProvider implements SchemaProviderInterface
{
    public function supports(mixed $object): bool
    {
        return $object instanceof BreadcrumbSchemaContext;
    }

    /**
     * @throws UnsupportedSchemaSubjectException
     */
    public function provide(mixed $object): ItemList
    {
        if (!$object instanceof BreadcrumbSchemaContext) {
            throw new UnsupportedSchemaSubjectException();
        }

        $elements = array_map(
            fn($p, $index) => $this->mapToListItem($p, $index + 1),
            $object->getItems(),
            array_keys($object->getItems()),
        );

        return Schema::itemList()
            ->itemListElement($elements)
        ;
    }

    private function mapToListItem(array $item, int $position): ListItem
    {
        return Schema::listItem()
            ->position($position)
            ->name(strip_tags($item['name'] ?? ''))
            ->item($item['url'])
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
