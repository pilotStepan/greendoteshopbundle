<?php

namespace Greendot\EshopBundle\Schema\Context;

class BreadcrumbSchemaContext
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
