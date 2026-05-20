<?php

namespace Greendot\EshopBundle\DataLayer\Event;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Contracts\EventDispatcher\Event;

class CheckoutFunnelEvent extends Event
{
    public const ViewCart   = 'view_cart';
    public const BeginCheckout   = 'begin_checkout';
    public const AddPaymentInfo  = 'add_payment_info';
    public const AddShippingInfo = 'add_shipping_info';

    public function __construct(
        private readonly Purchase $purchase,
        private readonly string   $type,
    ) {}

    public function getPurchase(): Purchase
    {
        return $this->purchase;
    }

    public function getType(): string
    {
        return $this->type;
    }
}