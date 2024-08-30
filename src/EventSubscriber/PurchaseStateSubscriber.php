<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Event\CompletedEvent;

class PurchaseStateSubscriber implements EventSubscriberInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.purchase_flow.guard.create'      => ['guardCreate'],
            'workflow.purchase_flow.transition.create' => ['transitionCreate'],
            'workflow.purchase_flow.completed.create'  => ['completedCreate'],
        ];
    }

    public function guardCreate(GuardEvent $event)
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        if ($purchase->getProductVariants()->isEmpty()) {
            $event->setBlocked(true, 'Cannot create an empty purchase.');
        }
    }

    public function transitionCreate(TransitionEvent $event)
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $purchase->setState('new');
    }

    public function completedCreate(CompletedEvent $event)
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        $this->entityManager->persist($purchase);
        $this->entityManager->flush();
    }
}
