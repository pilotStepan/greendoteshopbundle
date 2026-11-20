<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;

/**
 * Optional for notification handlers: describes the notification in the CMS transition preview.
 * Handlers without it are listed by their alias.
 */
interface DescribableNotificationInterface
{
    /**
     * @param string $transition the notification key (transition name, or its `notification_key` metadata)
     *
     * @return ActionDescription|null null when the handler sends nothing for this purchase
     */
    public function describe(Purchase $purchase, string $transition): ?ActionDescription;
}
