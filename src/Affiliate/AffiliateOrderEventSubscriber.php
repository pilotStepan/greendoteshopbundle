<?php

namespace Greendot\EshopBundle\Affiliate;

use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\HttpKernel\KernelEvents;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Utils\ApiRequestMatcher;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

readonly class AffiliateOrderEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AffiliateService $affiliateService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'setAffiliateCookies',

            PWC::eventName('transition', PWC::T_CHECKOUT) => 'assignAffiliateToPurchase',

            PWC::eventName('transition', PWC::T_PAY_PAY) => 'createAffiliateEntry',
            PWC::eventName('transition', PWC::T_COMPLETE) => 'createAffiliateEntry',
            PWC::eventName('transition', PWC::T_PAY_REFUND) => 'cancelAffiliateEntry',
            PWC::eventName('transition', PWC::T_CANCEL) => 'cancelAffiliateEntry',
        ];
    }

    public function setAffiliateCookies(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || ApiRequestMatcher::isApiRequest($event->getRequest())) {
            return;
        }

        $this->affiliateService->setAffiliateCookiesFromRequest($event);
    }

    public function assignAffiliateToPurchase(Event $event): void
    {
        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $this->affiliateService->setAffiliateToPurchase($purchase);
    }

    public function createAffiliateEntry(Event $event): void
    {
        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $this->affiliateService->dispatchCreateAffiliateEntryMessage($purchase);
    }

    public function cancelAffiliateEntry(Event $event): void
    {
        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $this->affiliateService->dispatchCancelAffiliateEntryMessage($purchase);
    }
}