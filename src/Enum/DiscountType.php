<?php

namespace Greendot\EshopBundle\Enum;

enum DiscountType: string
{
    case SingleClient = "SINGLE_CLIENT";
    case SingleUse = "SINGLE_USE";
    case MultiUse = "MULTI_USE";
}
