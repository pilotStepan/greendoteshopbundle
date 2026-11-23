<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

/**
 * Optional for notification handlers that send one e-mail: builds it without sending, so the CMS can show
 * the rendered e-mail before a transition. handle() should send exactly what this returns (plus attachments),
 * so the preview cannot drift from the real e-mail.
 *
 * Called inside PurchaseNotificationRuntime, like handle().
 */
interface PreviewableEmailNotificationInterface
{
    /**
     * @param string $transition the notification key (transition name, or its `notification_key` metadata)
     *
     * @return TemplatedEmail|null null when the handler sends nothing for this purchase
     */
    public function buildEmail(Purchase $purchase, string $transition): ?TemplatedEmail;
}
