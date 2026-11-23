<?php

namespace Greendot\EshopBundle\Service\PaymentGateway;

use Throwable;
use Symfony\Component\HttpFoundation\Request;

/**
 * Gateway whose return and notification callbacks go through the shared routes
 * `/order/payment/{gateway}/return` and `/order/payment/{gateway}/notify`.
 *
 * The gateway only identifies our payment and confirms its outcome with the provider;
 * locking, amount checks and purchase transitions are done by PaymentCallbackHandler.
 */
interface PaymentCallbackGatewayInterface extends PaymentGatewayInterface
{
    /**
     * @throws UnresolvedPaymentException when the callback does not belong to one of our payments
     * @throws Throwable when the outcome could not be determined (provider unreachable, ...)
     */
    public function resolveCallback(Request $request): PaymentResult;
}
