<?php

namespace Greendot\EshopBundle\Service\PaymentGateway;

use Greendot\EshopBundle\Enum\PaymentOutcome;
use Greendot\EshopBundle\Entity\Project\Payment;

/**
 * Outcome of a payment callback as confirmed by the gateway.
 */
final readonly class PaymentResult
{
    /**
     * @param Payment $payment
     * @param PaymentOutcome $outcome
     * @param int|null $amount
     * @param string|null $currency
     * @param array $raw
     */
    public function __construct(
        public Payment        $payment,
        public PaymentOutcome $outcome,
        public ?int           $amount = null,
        public ?string        $currency = null,
        public array          $raw = [],
    ) {}
}
