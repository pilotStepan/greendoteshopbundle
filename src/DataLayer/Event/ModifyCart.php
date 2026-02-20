<?php

namespace Greendot\EshopBundle\DataLayer\Event;

use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Symfony\Contracts\EventDispatcher\Event;

class ModifyCart extends Event
{
    public const Remove = 'remove';
    public const Add = 'add';

    public function __construct(
        private readonly PurchaseProductVariant $purchaseProductVariant,
        private readonly int $quantity,
        private readonly string $type,
    ){}

    public function getPurchaseProductVariant(): PurchaseProductVariant
    {
        return $this->purchaseProductVariant;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getType(): string
    {
        return $this->type;
    }




}