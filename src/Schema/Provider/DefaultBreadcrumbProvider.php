<?php

namespace App\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\ListItem;
use App\Schema\ObjectNotSupported;
use App\Schema\Dto\BreadcrumbContext;

/**
 * Provides structured data for breadcrumbs.
 */
class DefaultBreadcrumbProvider implements SchemaProviderInterface
{
    public function supports(mixed $object): bool
    {
        if ($object instanceof BreadcrumbContext) {
            return true;
        }

        return false;
    }

    /**
     * @throws ObjectNotSupported
     */
    public function provide(mixed $object): object|array|null
    {
        if (!$object instanceof BreadcrumbContext) {
            throw new ObjectNotSupported();
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

    public function getPriority(): int
    {
        return 0;
    }

    private function mapToListItem(array $item, int $position): ListItem
    {
        return Schema::listItem()
            ->position($position)
            ->name(strip_tags($item['name'] ?? ''))
            ->item($item['url'])
        ;
    }
}
