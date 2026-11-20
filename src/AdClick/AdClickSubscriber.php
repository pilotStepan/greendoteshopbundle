<?php

namespace Greendot\EshopBundle\AdClick;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionEffectDescriberInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Workflow\Event\Event;

readonly class AdClickSubscriber implements EventSubscriberInterface, PurchaseTransitionEffectDescriberInterface
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

    public function describeEffects(Purchase $purchase, Transition $transition, WorkflowInterface $workflow): iterable
    {
        if ($transition->getName() === PWC::T_CHECKOUT->value) {
            yield new ActionDescription('Přiřadit reklamní prokliky', category: ActionCategory::INTEGRATION);
        }
    }
}
