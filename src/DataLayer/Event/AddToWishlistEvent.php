<?php

namespace Greendot\EshopBundle\DataLayer\Event;

use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Symfony\Contracts\EventDispatcher\Event;

class AddToWishlistEvent extends Event
{
    public function __construct(
        private readonly PurchaseProductVariant $purchaseProductVariant,
        private readonly int                    $quantity,
    ) {}

    public function getPurchaseProductVariant(): PurchaseProductVariant
    {
        return $this->purchaseProductVariant;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}