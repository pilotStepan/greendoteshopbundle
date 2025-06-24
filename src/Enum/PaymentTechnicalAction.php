<?php

namespace Greendot\EshopBundle\Enum;

enum PaymentTechnicalAction: string
{
    case GLOBAL_PAYMENTS = 'gpw';
    case COMGATE = 'comgate';
}