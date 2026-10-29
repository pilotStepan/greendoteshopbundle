<?php

namespace Greendot\EshopBundle\Service\PaymentGateway\GoPay;

/**
 * Payment states as reported by the GoPay REST API.
 *
 * @see https://doc.gopay.com/#payment-states-and-expiration
 */
enum GoPayState: string
{
    case CREATED = 'CREATED';
    case PAYMENT_METHOD_CHOSEN = 'PAYMENT_METHOD_CHOSEN';
    case TIMEOUTED = 'TIMEOUTED';
    case PAID = 'PAID';
    case CANCELED = 'CANCELED';
    case AUTHORIZED = 'AUTHORIZED';
    case PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
    case REFUNDED = 'REFUNDED';

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    /**
     * Final negative outcome - the customer will not pay this payment anymore.
     */
    public function isFailed(): bool
    {
        return $this === self::CANCELED || $this === self::TIMEOUTED;
    }

    /**
     * The outcome is not known yet; GoPay will send a notification once it is.
     */
    public function isPending(): bool
    {
        return $this === self::CREATED || $this === self::PAYMENT_METHOD_CHOSEN;
    }
}
