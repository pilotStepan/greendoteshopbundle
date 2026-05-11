<?php

namespace Greendot\EshopBundle\Doctrine;

enum DoctrineFiltersConfigNames : string
{
    case ProductActiveFilter                                = "products_active";
    case ProductProductActiveFilter                         = "product_products_active";
    case ProductVariantActiveFilter                         = "variants_active";
    case TransportationIsEnabledFilter                      = "transportation_isEnabled";
    case CommentIsActiveFilter                              = "comment_isActive";
    case SoftDeletedFilter                                  = "soft_deleted_filter";
    case TransportationActionTransportationIsEnabledFilter  = "transportationAction_transportation_isEnabled";
}