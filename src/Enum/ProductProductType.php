<?php

namespace Greendot\EshopBundle\Enum;

enum ProductProductType : string
{
    /** A product that is intended to be bought with the parent product. Shows near "add to cart" */
    case Complement = "COMPLEMENT";
    /** A product that is intended to be shown as a recommendation with the parent product. Shows in product detail as "related products"  */
    case  Related = "RELATED";
}