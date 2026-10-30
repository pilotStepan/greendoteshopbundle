<?php

namespace Greendot\EshopBundle\Enum;

enum PaymentOutcome: string
{
    case PAID = 'paid';
    case FAILED = 'failed';
    case PENDING = 'pending';
}
