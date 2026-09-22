<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\HttpKernel\KernelEvents;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\RefererTracker;
use Greendot\EshopBundle\Utils\ApiRequestMatcher;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

class RefererSubscriber implements EventSubscriberInterface
{
    public function __construct(private RefererTracker $refererTracker) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'captureRefererCookie',

            PWC::eventName('transition', PWC::T_CHECKOUT) => 'assignRefererToPurchase',
        ];
    }

    public function captureRefererCookie(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || ApiRequestMatcher::isApiRequest($event->getRequest())) {
            return;
        }

        $this->refererTracker->captureRefererCookie($event);
    }

    public function assignRefererToPurchase(Event $event): void
    {
        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $this->refererTracker->setRefererToPurchase($purchase);
    }
}
