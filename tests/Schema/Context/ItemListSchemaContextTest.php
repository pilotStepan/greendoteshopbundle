<?php

namespace Greendot\EshopBundle\Tests\Schema\Context;

use PHPUnit\Framework\TestCase;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Greendot\EshopBundle\Schema\Context\ItemListSchemaContext;

class ItemListSchemaContextTest extends TestCase
{
    private CategoryEntity $category;

    protected function setUp(): void
    {
        $this->category = $this->createMock(CategoryEntity::class);
    }

    public function testGetOffsetFirstPage(): void
    {
        $ctx = new ItemListSchemaContext($this->category, page: 1, itemsPerPage: 30);
        $this->assertSame(0, $ctx->getOffset());
    }

    public function testGetOffsetSecondPage(): void
    {
        $ctx = new ItemListSchemaContext($this->category, page: 2, itemsPerPage: 30);
        $this->assertSame(30, $ctx->getOffset());
    }

    public function testGetOffsetWithCustomItemsPerPage(): void
    {
        $ctx = new ItemListSchemaContext($this->category, page: 3, itemsPerPage: 10);
        $this->assertSame(20, $ctx->getOffset());
    }

    public function testDefaultValuesApply(): void
    {
        $ctx = new ItemListSchemaContext($this->category);
        $this->assertSame(1, $ctx->page);
        $this->assertSame(30, $ctx->itemsPerPage);
        $this->assertSame(0, $ctx->getOffset());
    }
}
