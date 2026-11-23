<?php

namespace Greendot\EshopBundle\Service\PaymentGateway\GoPay;

/**
 * Result of a GoPay payment status inquiry (GET /payments/payment/{id}).
 */
final readonly class GoPayPaymentStatus
{
    /**
     * @param string $id
     * @param GoPayState|null $state
     * @param string|null $orderNumber
     * @param int|null $amount
     * @param string|null $currency
     * @param array $raw
     * @param string $rawState
     */
    public function __construct(
        public string      $id,
        public ?GoPayState $state,
        public ?string     $orderNumber,
        public ?int        $amount,
        public ?string     $currency,
        public array       $raw = [],
        public string      $rawState = '',
    ) {}

    public static function fromResponse(array $data): self
    {
        $rawState = (string)($data['state'] ?? '');

        return new self(
            (string)$data['id'],
            GoPayState::tryFrom($rawState),
            isset($data['order_number']) ? (string)$data['order_number'] : null,
            isset($data['amount']) ? (int)$data['amount'] : null,
            $data['currency'] ?? null,
            $data,
            $rawState,
        );
    }
}
