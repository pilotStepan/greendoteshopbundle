<?php

namespace Greendot\EshopBundle\Enum;

enum ParcelDeliveryState: string
{
    // these are just examples, change them...
    case TRANSMITTED_DATA = 'transmitted_data';
    case SUBMITTED = 'submitted';

    public function isFinal(): bool
    {
        return match ($this) {
            self::SUBMITTED => true,
            default         => false,
        };
    }
}
