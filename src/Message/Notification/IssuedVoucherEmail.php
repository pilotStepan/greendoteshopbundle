<?php

namespace Greendot\EshopBundle\Message\Notification;

readonly class IssuedVoucherEmail
{
    public function __construct(public int $voucherId) {}
}