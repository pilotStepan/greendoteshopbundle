<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderItemData
{
    public function __construct(
        public int    $productId,
        public string $name,
        public string $productSlug,
        public int    $quantity,
        public string $unitPrice,
        public string $totalPrice,
    ) {}
}