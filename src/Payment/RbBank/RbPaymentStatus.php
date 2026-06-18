<?php

namespace Greendot\EshopBundle\Payment\RbBank;

// status codes documented by Raiffeisenbank's "Modul platebního systému" payments-list report
enum RbPaymentStatus: int
{
    case Unrealized = 0;
    case Completed = 2;
    case Terminated = 4;
}
