<?php

namespace Greendot\EshopBundle\Message\Watchdog\VariantAvailable;

class VariantAvailableEmail
{
    public function __construct(
        public int    $watchdogId,
        public int    $productVariantId,
        public string $email,
        public string $eventKey,
    ) {}
}
