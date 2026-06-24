<?php

namespace Greendot\EshopBundle\Enum;

enum PaymentActionType: string
{
    case GPW_REDIRECT = 'gpw_redirect';
    case GPW_RETURN = 'gpw_return';
    case STATE_PAID = 'state_paid';
    case STATE_FAILED = 'state_failed';
    case STATE_REFUNDED = 'state_refunded';
    case STATE_CANCELLED = 'state_cancelled';
    case FAILURE = 'failure';
}
