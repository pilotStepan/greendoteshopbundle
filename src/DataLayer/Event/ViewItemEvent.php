<?php

namespace Greendot\EshopBundle\DataLayer\Event;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Symfony\Contracts\EventDispatcher\Event;

class ViewItemEvent extends Event
{
    public function __construct(
        private readonly ProductVariant $productVariant,
    ){}

    public function getProductVariant(): ProductVariant
    {
        return $this->productVariant;
    }
}