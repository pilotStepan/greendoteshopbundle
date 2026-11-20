<?php

namespace Greendot\EshopBundle\Affiliate;

use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\HttpKernel\KernelEvents;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Utils\ApiRequestMatcher;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionEffectDescriberInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

readonly class AffiliateOrderEventSubscriber implements EventSubscriberInterface, PurchaseTransitionEffectDescriberInterface
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

    public function describeEffects(Purchase $purchase, Transition $transition, WorkflowInterface $workflow): iterable
    {
        $name = $transition->getName();

        if ($name === PWC::T_CHECKOUT->value) {
            yield new ActionDescription('Přiřadit affiliate partnera', category: ActionCategory::INTEGRATION);
            return;
        }

        if ($purchase->getAffiliateId() === null) {
            return;
        }

        if (in_array($name, [PWC::T_PAY_PAY->value, PWC::T_COMPLETE->value], true)) {
            yield new ActionDescription('Zapsat provizi partnerovi', detail: $purchase->getAffiliateId(), category: ActionCategory::INTEGRATION);
        } elseif (in_array($name, [PWC::T_PAY_REFUND->value, PWC::T_CANCEL->value], true)) {
            yield new ActionDescription('Zrušit provizi partnera', detail: $purchase->getAffiliateId(), category: ActionCategory::INTEGRATION);
        }
    }
}
