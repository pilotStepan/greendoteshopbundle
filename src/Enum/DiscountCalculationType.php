<?php
namespace Greendot\EshopBundle\Enum;

enum DiscountCalculationType: string
{
    case WithDiscount = "Price including conventional discounts";
    case WithoutDiscount = "Price excluding conventional discounts";
    case OnlyProductDiscount = "Price including only discounts on product variants, ignores client and other discounts";
    case WithDiscountPlusAfterRegistrationDiscount = "Price including conventional discounts plus discount after registration";
    case WithoutDiscountPlusAfterRegistrationDiscount = "Price excluding conventional discounts plus discount after registration";
}