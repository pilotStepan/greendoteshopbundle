<?php
namespace Greendot\EshopBundle\Enum;

enum DiscountAmountType : string
{
    case Percentage = "Returns discount in percentage";

    //with price of 200 and discount 15% returns 30
    case Amount = "Returns amount of discount in currency";
}