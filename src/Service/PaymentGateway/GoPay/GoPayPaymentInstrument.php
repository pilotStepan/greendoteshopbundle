<?php

namespace Greendot\EshopBundle\Service\PaymentGateway\GoPay;

/**
 * Payment methods the GoPay gateway can offer (`payer.allowed_payment_instruments`).
 * Which of them actually show up also depends on what is enabled for the GoID and the payment currency.
 *
 * @see https://github.com/gopaycommunity/gopay-php-api/blob/master/src/Definition/Payment/PaymentInstrument.php
 */
enum GoPayPaymentInstrument: string
{
    case PAYMENT_CARD = 'PAYMENT_CARD';
    case APPLE_PAY = 'APPLE_PAY';
    case GPAY = 'GPAY';
    case CLICK_TO_PAY = 'CLICK_TO_PAY';
    case BANK_ACCOUNT = 'BANK_ACCOUNT';
    case PRSMS = 'PRSMS';
    case MPAYMENT = 'MPAYMENT';
    case PAYSAFECARD = 'PAYSAFECARD';
    case SUPERCASH = 'SUPERCASH';
    case GOPAY = 'GOPAY';
    case PAYPAL = 'PAYPAL';
    case BITCOIN = 'BITCOIN';
    case ACCOUNT = 'ACCOUNT';
    case TWISTO = 'TWISTO';
    case SKIPPAY = 'SKIPPAY';
}
