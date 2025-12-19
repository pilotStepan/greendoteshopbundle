<?php

namespace Greendot\EshopBundle\Sms\Factory;

use Throwable;
use Greendot\EshopBundle\Dto\SmsMessageDto;
use Greendot\EshopBundle\Entity\Project\Purchase;

interface OrderTransitionSmsFactoryInterface
{
    /* @throws Throwable */
    public function create(Purchase $purchase, string $transition): SmsMessageDto;
}