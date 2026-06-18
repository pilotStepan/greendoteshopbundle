<?php

namespace Greendot\EshopBundle\DataLayer\Event;

use Greendot\EshopBundle\Entity\Project\Product;
use Symfony\Contracts\EventDispatcher\Event;

class ViewItemListProductEvent extends Event
{
    public function __construct(
        private readonly Product $product
    ){}

    public function getProduct(): Product
    {
        return $this->product;
    }
}