<?php

namespace Greendot\EshopBundle\DataLayer\Event;

use Greendot\EshopBundle\Entity\Project\Product;
use Symfony\Contracts\EventDispatcher\Event;

class ViewItemEvent extends Event
{
    public function __construct(
        private readonly Product $product,
        private readonly ?array $selectedVariants = null
    ){}

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getSelectedVariants(): ?array
    {
        return $this->selectedVariants;
    }
}