<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * A context object for breadcrumbs to be used with collect_structured_data.
 */
class BreadcrumbContext
{
    /** @var array */
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}
