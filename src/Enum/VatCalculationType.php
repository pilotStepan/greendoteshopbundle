<?php
namespace Greendot\EshopBundle\Enum;

enum VatCalculationType: string
{
    case WithVAT = "Price including VAT";
    case WithoutVAT = "Price excluding VAT";
    case OnlyVAT = "Calculate only VAT amount";
}