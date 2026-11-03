<?php

namespace Greendot\EshopBundle\Enum;

enum PaymentActionType: string
{
    case GPW_REDIRECT = 'gpw_redirect';
    case GPW_RETURN = 'gpw_return';
    case GOPAY_REDIRECT = 'gopay_redirect';
    case GOPAY_RETURN = 'gopay_return';
    case GOPAY_NOTIFY = 'gopay_notify';
    case STATE_PAID = 'state_paid';
    case STATE_FAILED = 'state_failed';
    case STATE_REFUNDED = 'state_refunded';
    case STATE_CANCELLED = 'state_cancelled';
    case FAILURE = 'failure';
}
