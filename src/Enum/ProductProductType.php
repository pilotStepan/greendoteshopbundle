<?php

namespace Greendot\EshopBundle\Enum;

enum ProductProductType : string
{
    case Complement = "COMPLEMENT"; // zobrazuje se u "přidat do košíku"
    case  Related = "RELATED"; // zobrazuje se v detailu produktu jakožto "související produkty"
}