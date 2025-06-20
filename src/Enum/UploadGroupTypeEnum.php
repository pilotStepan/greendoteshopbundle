<?php

namespace Greendot\EshopBundle\Enum;

enum UploadGroupTypeEnum : int
{
    /** Image for the product */
     case IMAGE = 0;
     /** Download file for the product */
     case ATTACHMENT = 1;
     /** Video file to be shown in the product detail */
     case VIDEO = 2;
     /** Audio file to be shown in the product detail */
     case AUDIO = 3;
}
