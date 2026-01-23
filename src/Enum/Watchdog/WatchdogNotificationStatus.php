<?php

namespace Greendot\EshopBundle\Enum\Watchdog;

enum WatchdogNotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
