<?php

namespace Greendot\EshopBundle\Enum;

enum DownloadRestriction: int
{
    case Default = 0;
    case NoRestrictions = 1;
    case EmailRequired = 2;
    case ConfirmationRequired = 3;
}
