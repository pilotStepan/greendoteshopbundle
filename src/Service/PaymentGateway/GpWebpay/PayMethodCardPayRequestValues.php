<?php

namespace Greendot\EshopBundle\Service\PaymentGateway\GpWebpay;

use Granam\GpWebPay\CardPayRequestValues;
use Granam\GpWebPay\Codes\CurrencyCodes;
use Greendot\EshopBundle\Enum\PaymentPayMethod;

/**
 * granam/gpwebpay validates PAYMETHOD against an outdated list without GPAY/APAY and that check is private.
 * CardPayRequest reads the value through the public getter when it builds the signed request, so the
 * value is supplied here instead, keeping its documented position in the digest.
 */
class PayMethodCardPayRequestValues extends CardPayRequestValues
{
    private ?PaymentPayMethod $gatewayPayMethod = null;

    public static function createWithPayMethod(
        array $values,
        CurrencyCodes $currencyCodes,
        ?PaymentPayMethod $payMethod,
    ): self {
        /** @var self $requestValues */
        $requestValues = static::createFromArray($values, $currencyCodes);
        $requestValues->gatewayPayMethod = $payMethod;

        return $requestValues;
    }

    public function getPayMethod(): ?string
    {
        return $this->gatewayPayMethod?->value;
    }
}
