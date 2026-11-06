<?php

namespace Greendot\EshopBundle\Enum;

/**
 * Payment method a PaymentType asks the gateway to open with.
 * Values are the GP webpay PAYMETHOD codes (GP webpay HTTP API, Annex 5).
 */
enum PaymentPayMethod: string
{
    case GOOGLE_PAY = 'GPAY';
    case APPLE_PAY = 'APAY';
}
