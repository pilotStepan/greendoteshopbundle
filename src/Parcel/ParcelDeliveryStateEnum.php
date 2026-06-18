<?php

namespace Greendot\EshopBundle\Parcel;

enum ParcelDeliveryStateEnum: string
{
    case RECEIVED_DATA    = 'received_data';    // Packeta 1
    case IN_TRANSIT       = 'in_transit';       // Packeta 2,3,4
    case READY_FOR_PICKUP = 'ready_for_pickup'; // Packeta 5
    case DELIVERED        = 'delivered';        // Packeta 7
    case NOT_PICKED_UP    = 'not_picked_up';    // Packeta 8
    case CANCELLED        = 'cancelled';        // Packeta 9+

    public function isFinal(): bool
    {
        return match ($this) {
            self::DELIVERED, self::NOT_PICKED_UP, self::CANCELLED => true,
            default => false,
        };
    }
}
