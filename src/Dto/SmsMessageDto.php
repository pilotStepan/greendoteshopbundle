<?php

namespace Greendot\EshopBundle\Dto;

final readonly class SmsMessageDto
{
    public function __construct(
        public string  $phone,
        public string  $text,
        public ?string $sender = null,
    ) {}
}