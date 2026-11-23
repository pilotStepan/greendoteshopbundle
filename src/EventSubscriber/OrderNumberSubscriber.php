<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Symfony\Component\Workflow\Event\Event;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionEffectDescriberInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Gives a purchase its customer-facing order number at the moment it becomes a real order.
 */
readonly class OrderNumberSubscriber implements EventSubscriberInterface, PurchaseTransitionEffectDescriberInterface
{
    public function __construct(private PurchaseRepository $purchaseRepository) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PWC::eventName('transition', PWC::T_CHECKOUT) => 'assignOrderNumber',
            PWC::eventName('transition', PWC::T_INIT_ORDER) => 'assignOrderNumber',
        ];
    }

    public function assignOrderNumber(Event $event): void
    {
        /* @var Purchase $purchase */
        $purchase = $event->getSubject();
        $this->purchaseRepository->assignNextOrderNumber($purchase);
    }

    public function describeEffects(Purchase $purchase, Transition $transition, WorkflowInterface $workflow): iterable
    {
        if (in_array($transition->getName(), [PWC::T_CHECKOUT->value, PWC::T_INIT_ORDER->value], true)
            && $purchase->getOrderNumber() === null) {
            yield new ActionDescription('Přidělit číslo objednávky', category: ActionCategory::STATE);
        }
    }
}
