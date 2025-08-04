<?php

namespace Greendot\EshopBundle\Enum;

enum TransportationAction: string
{
    case BOX = 'box'; // doručení na výdejní místo
    case DELIVERY = 'delivery'; // doručení na adresu
    case PICKUP = 'pickup'; // osobní odběr
    case COURIER = 'courier'; // doručení kurýrem
}
