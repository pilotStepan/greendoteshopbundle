<?php

namespace Greendot\EshopBundle\Enum;

enum ProductViewTypeEnum: int
{
    case HTML = 1;

    case ESHOP = 2;
    case CATALOGUE = 3;
}