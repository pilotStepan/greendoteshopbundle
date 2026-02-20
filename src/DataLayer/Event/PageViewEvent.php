<?php

namespace Greendot\EshopBundle\DataLayer\Event;

use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Contracts\EventDispatcher\Event;

class PageViewEvent extends Event
{
    public function __construct(
        private readonly Category $category
    ){}

    public function getCategory(): Category
    {
        return $this->category;
    }
}