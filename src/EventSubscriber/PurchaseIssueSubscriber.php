<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

/**
 * Issues a purchase once it has become a real order.
 *
 * Runs on `completed.<transition>`, i.e. only after the transition fully succeeded and the new marking is stored.
 * The priority puts it before other `completed.<transition>` listeners. Listeners on the generic
 * `workflow.purchase_flow.completed` (e.g. PurchaseNotificationSubscriber) are dispatched before it and do not see the date.
 */
readonly class PurchaseIssueSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 1024;

    public static function getSubscribedEvents(): array
    {
        return [
            PWC::eventName('completed', PWC::T_CHECKOUT) => ['issue', self::PRIORITY],
            PWC::eventName('completed', PWC::T_INIT_ORDER) => ['issue', self::PRIORITY],
        ];
    }

    public function issue(Event $event): void
    {
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $purchase->issue();
    }
}
