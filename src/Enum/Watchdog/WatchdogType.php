<?php

namespace Greendot\EshopBundle\Enum\Watchdog;

/**
 * Keep this enum small and stable. New watchdogs should be appended and handled
 * by dedicated message + handler pairs.
 */
enum WatchdogType: string
{
    case VariantAvailable = 'variant_available';
}
