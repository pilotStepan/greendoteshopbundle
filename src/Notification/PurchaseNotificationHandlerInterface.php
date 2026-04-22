<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('greendot_eshop.purchase_notification')]
interface PurchaseNotificationHandlerInterface
{
    public function handle(Purchase $purchase, string $transition): void;
}
