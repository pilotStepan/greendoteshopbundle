<?php

namespace Greendot\EshopBundle\Parcel;

enum ParcelDeliveryStateEnum: string
{
    case RECEIVED_DATA = 'received_data';
    case IN_TRANSIT = 'in_transit';
    case READY_FOR_PICKUP = 'ready_for_pickup';
    case DELIVERED = 'delivered';
    case NOT_PICKED_UP = 'not_picked_up';
    case CANCELLED = 'cancelled';

    public function isFinal(): bool
    {
        return match ($this) {
            self::DELIVERED, self::NOT_PICKED_UP, self::CANCELLED => true,
            default                                               => false,
        };
    }
}
