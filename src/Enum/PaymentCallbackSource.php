<?php

namespace Greendot\EshopBundle\Enum;

/**
 * How a payment callback reached us.
 */
enum PaymentCallbackSource: string
{
    /* The client's browser was redirected back from the gateway */
    case RETURN = 'return';

    /* Server-to-server notification sent by the gateway */
    case NOTIFY = 'notify';

    public function performedBy(): string
    {
        return $this === self::RETURN ? 'client' : 'system';
    }
}
