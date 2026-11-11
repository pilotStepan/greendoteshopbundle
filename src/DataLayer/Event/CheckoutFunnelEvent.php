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

    public const TYPES = [self::ViewCart, self::BeginCheckout, self::AddPaymentInfo, self::AddShippingInfo];

    /** @var self::ViewCart|self::BeginCheckout|self::AddPaymentInfo|self::AddShippingInfo */
    private readonly string $type;

    public function __construct(
        private readonly Purchase $purchase,
        string $type,
    ) {
        if (!\in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown checkout funnel type "%s".', $type));
        }
        $this->type = $type;
    }

    public function getPurchase(): Purchase
    {
        return $this->purchase;
    }

    /** @return self::ViewCart|self::BeginCheckout|self::AddPaymentInfo|self::AddShippingInfo */
    public function getType(): string
    {
        return $this->type;
    }
}