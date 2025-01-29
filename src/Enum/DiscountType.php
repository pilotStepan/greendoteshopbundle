<?php

namespace Greendot\EshopBundle\Enum;

enum DiscountType: string
{
    case SingleClient = "SINGLE_CLIENT"; // pro jednoho clienta, více použití
    case SingleUse = "SINGLE_USE"; // pro jedno použití
    case MultiUse = "MULTI_USE"; // pro více použítí
    case SingleUseClient = "SINGLE_USE_CLIENT"; // pro jenoho clienta, jedno použití
}
