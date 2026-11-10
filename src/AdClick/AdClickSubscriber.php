<?php

namespace Greendot\EshopBundle\AdClick;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Workflow\Event\Event;

readonly class AdClickSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AdClickTracker $adClickTracker,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'clearCookieAfterCheckout',

            PWC::eventName('transition', PWC::T_CHECKOUT) => 'assignAdClicksToOrder',
        ];
    }

    public function clearCookieAfterCheckout(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->adClickTracker->clearCookieAfterCheckout($event);
    }

    public function assignAdClicksToOrder(Event $event): void
    {
        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $this->adClickTracker->assignToOrder($purchase);
    }
}
