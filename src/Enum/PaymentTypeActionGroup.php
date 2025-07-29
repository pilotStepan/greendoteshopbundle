<?php

namespace Greendot\EshopBundle\Enum;

enum PaymentTypeActionGroup: int
{
    case CARD_PAYMENT = 1;  // kartou
    case BANK_TRANSFER = 2; // bankovnim převodem
    case CASH = 3;          // hotově
    case ON_DELIVERY = 4;   // dobírkou
}