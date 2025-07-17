<?php

namespace Greendot\EshopBundle\Enum;

enum PaymentTypeActionGroup: int
{
    case CASH_AND_DELIVERY = 0;
    case ONLINE_PAYMENT = 1;
}