<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Drives automatic internal funnel transitions for the purchase workflow.
 *
 * After logistics or payment track transitions complete, this subscriber:
 *  - fires T_LOG_TO_DONE after T_LOG_SEND (log_shipped → adds log_track_done)
 *  - attempts T_COMPLETE whenever either track finishes (AND-join fires when both done)
 *
 * This subscriber should not be overridden in consuming apps unless the workflow
 * definition itself is fundamentally changed. The auto-complete behaviour is part
 * of the bundle contract.
 */
final readonly class PurchaseWorkflowFunnelSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Target('purchase_flow')]
        private WorkflowInterface      $purchaseWorkflow,
        private EntityManagerInterface $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // After log_send: auto-advance to log_track_done, then attempt completion
            PWC::eventName('completed', PWC::T_LOG_SEND) => 'onLogSendCompleted',
            // After pickup confirmed: attempt completion (log_track_done already set by the transition)
            PWC::eventName('completed', PWC::T_LOG_PICKUP_DONE) => 'onTrackFinished',
            // After payment succeeds (from pending or retry): attempt completion
            PWC::eventName('completed', PWC::T_PAY_PAY) => 'onTrackFinished',
            PWC::eventName('completed', PWC::T_PAY_RETRY) => 'onTrackFinished',
        ];
    }

    public function onLogSendCompleted(CompletedEvent $event): void
    {
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        if ($this->purchaseWorkflow->can($purchase, PWC::T_LOG_TO_DONE->value)) {
            $this->purchaseWorkflow->apply($purchase, PWC::T_LOG_TO_DONE->value, ['silent' => true]);
        }

        $this->tryAutoComplete($purchase);
        $this->entityManager->flush();
    }

    public function onTrackFinished(CompletedEvent $event): void
    {
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->tryAutoComplete($purchase);
        $this->entityManager->flush();
    }

    private function tryAutoComplete(Purchase $purchase): void
    {
        if ($this->purchaseWorkflow->can($purchase, PWC::T_COMPLETE->value)) {
            $this->purchaseWorkflow->apply($purchase, PWC::T_COMPLETE->value);
        }
    }
}
