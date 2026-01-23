<?php

namespace Greendot\EshopBundle\Enum\Watchdog;

enum WatchdogState: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
