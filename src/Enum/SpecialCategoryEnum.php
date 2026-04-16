<?php

namespace Greendot\EshopBundle\Enum;

enum SpecialCategoryEnum : string
{
    case HOMEPAGE = "HOMEPAGE";
    case BLOG = "BLOG";
    case NOT_FOUND = "NOT_FOUND";
    case ADVISORY = "ADVISORY";
    case FOOTER = "FOOTER";
    case PRODUCERS_LANDING = "PRODUCERS_LANDING";
    case EVENTS_LANDING = "EVENTS_LANDING";
    case DISCOUNTS_LANDING = "DISCOUNTS_LANDING";
    case GDRP_LANDING = "GDPR_LANDING";
    case GALLERY_LANDING = "GALLERY_LANDING";
}