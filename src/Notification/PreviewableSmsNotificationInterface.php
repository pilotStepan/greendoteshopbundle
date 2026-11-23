<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use Greendot\EshopBundle\Dto\SmsMessageDto;
use Greendot\EshopBundle\Entity\Project\Purchase;

/**
 * Optional for notification handlers that send an SMS: builds it without sending, so the CMS transition
 * preview can show the text. Built from the same factory handle() sends from.
 *
 * Called inside PurchaseNotificationRuntime, like handle().
 */
interface PreviewableSmsNotificationInterface
{
    /**
     * @param string $transition the notification key (transition name, or its `notification_key` metadata)
     *
     * @return SmsMessageDto|null null when the handler sends nothing for this purchase (e.g. no valid phone)
     *
     * @throws \Throwable when the text cannot be built
     */
    public function buildSms(Purchase $purchase, string $transition): ?SmsMessageDto;
}
