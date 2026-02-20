<?php

namespace Greendot\EshopBundle\DataLayer\Event;

use Symfony\Contracts\EventDispatcher\Event;

class PurchaseEvent extends Event
{
    public function __construct(
        private readonly \Greendot\EshopBundle\Entity\Project\Purchase $purchase
    ){}

    public function getPurchase(): \Greendot\EshopBundle\Entity\Project\Purchase
    {
        return $this->purchase;
    }
}