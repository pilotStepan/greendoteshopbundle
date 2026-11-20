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
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionEffectDescriberInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

class RefererSubscriber implements EventSubscriberInterface, PurchaseTransitionEffectDescriberInterface
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

    public function describeEffects(Purchase $purchase, Transition $transition, WorkflowInterface $workflow): iterable
    {
        if ($transition->getName() === PWC::T_CHECKOUT->value) {
            yield new ActionDescription('Uložit zdroj návštěvy', category: ActionCategory::INTEGRATION);
        }
    }
}
