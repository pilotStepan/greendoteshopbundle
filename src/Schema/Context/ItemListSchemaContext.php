<?php

namespace Greendot\EshopBundle\Schema\Context;

use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;

class ItemListSchemaContext
{
    public function __construct(
        public readonly CategoryEntity $category,
        public readonly int            $page = 1,
        public readonly int            $itemsPerPage = 30,
    ) {}

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->itemsPerPage;
    }
}
