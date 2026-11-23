<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

enum ActionKind: string
{
    /** A PurchaseActionInterface service, run synchronously. */
    case ACTION = 'action';
    /** A PurchaseNotificationHandlerInterface service, dispatched as an async message. */
    case NOTIFICATION = 'notification';
}
