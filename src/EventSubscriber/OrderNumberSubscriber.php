<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Symfony\Component\Workflow\Event\Event;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

/**
 * Gives a purchase its customer-facing order number at the moment it becomes a real order.
 */
readonly class OrderNumberSubscriber implements EventSubscriberInterface
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
}
