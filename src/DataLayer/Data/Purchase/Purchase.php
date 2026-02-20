<?php

namespace Greendot\EshopBundle\DataLayer\Data\Purchase;

class Purchase
{
    public function __construct(
        public string $transaction_id,
        public float $value,
        public float $tax,
        public float $shipping,
        public string $currency,
        public string $customer_type, // new || returning

        /**
         * @var PurchaseItem[]
         */
        public array $items
    ){}
}