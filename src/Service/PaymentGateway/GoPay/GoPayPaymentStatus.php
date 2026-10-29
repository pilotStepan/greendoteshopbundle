<?php

namespace Greendot\EshopBundle\Service\PaymentGateway\GoPay;

/**
 * Result of a GoPay payment status inquiry (GET /payments/payment/{id}).
 */
final readonly class GoPayPaymentStatus
{
    public function __construct(
        public string     $id,
        public GoPayState $state,
        public ?string    $orderNumber,
        public ?int       $amount,
        public ?string    $currency,
        public array      $raw = [],
    ) {}

    public static function fromResponse(array $data): self
    {
        return new self(
            (string)$data['id'],
            GoPayState::from($data['state']),
            isset($data['order_number']) ? (string)$data['order_number'] : null,
            isset($data['amount']) ? (int)$data['amount'] : null,
            $data['currency'] ?? null,
            $data,
        );
    }
}
