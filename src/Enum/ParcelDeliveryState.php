<?php

namespace Greendot\EshopBundle\Enum;

enum ParcelDeliveryState: string
{
    // these are just examples, change them...
    case IN_TRANSIT = 'in_transit';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';

    public function isFinal(): bool
    {
        return match ($this) {
            self::DELIVERED,
            self::FAILED,
            self::CANCELLED,
            self::RETURNED => true,
            default        => false,
        };
    }
}
