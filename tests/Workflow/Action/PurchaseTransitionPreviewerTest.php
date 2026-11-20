<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Workflow\Action;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionEffectDescriberInterface;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionPreviewer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

class PurchaseTransitionPreviewerTest extends TestCase
{
    use TransitionActionTestTrait;

    private function previewer(WorkflowInterface $workflow): PurchaseTransitionPreviewer
    {
        $describer = new class implements PurchaseTransitionEffectDescriberInterface {
            public function describeEffects(Purchase $purchase, Transition $transition, WorkflowInterface $workflow): iterable
            {
                yield new ActionDescription('Systém', 'detail', ActionCategory::STATE);
            }
        };

        $resolver = $this->buildResolver(
            ['invoice.issue' => $this->recordingAction('invoice.issue', 'Vystavení faktury'), 'never' => $this->recordingAction('never')],
            ['purchase_notification.customer_email' => $this->plainNotification()],
            [$describer],
        );

        return new PurchaseTransitionPreviewer($workflow, $resolver, new IdentityTranslator());
    }

    public function testPreviewOfEnabledTransition(): void
    {
        $workflow = $this->buildWorkflow([
            'label' => 'Odeslat',
            'actions' => ['invoice.issue', 'never'],
            'notifications' => ['purchase_notification.customer_email'],
        ]);

        $preview = $this->previewer($workflow)->preview($this->purchase(), 'ship')->jsonSerialize();

        $this->assertSame([
            'transition' => 'ship',
            'label' => 'Odeslat',
            'can' => true,
            'blockers' => [],
            'actions' => [
                ['key' => 'invoice.issue', 'skippable' => false, 'email_preview' => false, 'label' => 'Vystavení faktury', 'detail' => null, 'category' => 'other'],
                ['key' => 'purchase_notification.customer_email', 'skippable' => true, 'email_preview' => false, 'label' => 'purchase_notification.customer_email', 'detail' => null, 'category' => 'other'],
            ],
            'system' => [
                ['label' => 'Systém', 'detail' => 'detail', 'category' => 'state'],
            ],
        ], $preview);
    }

    public function testBlockedTransitionListsGuardMessages(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.purchase_flow.guard.ship', static fn (GuardEvent $e) => $e->setBlocked(true, 'Chybí doprava'));
        $workflow = $this->buildWorkflow(['actions' => ['invoice.issue']], $dispatcher);

        $preview = $this->previewer($workflow)->preview($this->purchase(), 'ship');

        $this->assertFalse($preview->can);
        $this->assertSame(['Chybí doprava'], $preview->blockers);
        $this->assertSame('ship', $preview->label);
        $this->assertCount(1, $preview->actions, 'actions are listed even when blocked');
    }

    public function testUnknownTransition(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->previewer($this->buildWorkflow([]))->preview($this->purchase(), 'nope');
    }
}
