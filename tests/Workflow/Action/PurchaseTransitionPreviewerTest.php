<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Workflow\Action;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionEffectDescriberInterface;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionPreviewer;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use Greendot\EshopBundle\Dto\SmsMessageDto;
use Greendot\EshopBundle\Notification\PreviewableSmsNotificationInterface;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Greendot\EshopBundle\Notification\PurchaseNotificationRuntime;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Symfony\Component\Translation\LocaleSwitcher;
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
            [
                'purchase_notification.customer_email' => $this->plainNotification(),
                'purchase_notification.sms_ok' => $this->smsHandler(static fn () => new SmsMessageDto('420777123456', 'Objednávka 1 je zaplacena.')),
                'purchase_notification.sms_none' => $this->smsHandler(static fn () => null),
                'purchase_notification.sms_fail' => $this->smsHandler(static fn () => throw new \RuntimeException('Empty SMS text generated')),
            ],
            [$describer],
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($this->createMock(FilterCollection::class));

        return new PurchaseTransitionPreviewer(
            $workflow,
            $resolver,
            new IdentityTranslator(),
            new PurchaseNotificationRuntime(new PurchaseLocaleResolver('cs'), new LocaleSwitcher('cs', []), $em),
        );
    }

    /**
     * @param \Closure(): ?SmsMessageDto $build
     */
    private function smsHandler(\Closure $build): PurchaseNotificationHandlerInterface
    {
        return new class($build) implements PurchaseNotificationHandlerInterface, PreviewableSmsNotificationInterface {
            public function __construct(private \Closure $build) {}

            public function buildSms(Purchase $purchase, string $transition): ?SmsMessageDto
            {
                return ($this->build)();
            }

            public function handle(Purchase $purchase, string $transition): void {}
        };
    }

    public function testSmsTextIsInlinedErrorsAreReportedAndNoSmsIsNull(): void
    {
        $workflow = $this->buildWorkflow([
            'notifications' => ['purchase_notification.sms_ok', 'purchase_notification.sms_none', 'purchase_notification.sms_fail'],
        ]);

        $actions = $this->previewer($workflow)->preview($this->purchase(), 'ship')->actions;

        $this->assertSame(
            [
                ['text' => 'Objednávka 1 je zaplacena.'],
                null,
                ['error' => 'Text SMS nelze sestavit: Empty SMS text generated'],
            ],
            array_column($actions, 'sms'),
        );
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
                ['key' => 'invoice.issue', 'skippable' => false, 'email_preview' => false, 'sms' => null, 'label' => 'Vystavení faktury', 'detail' => null, 'category' => 'other'],
                ['key' => 'purchase_notification.customer_email', 'skippable' => true, 'email_preview' => false, 'sms' => null, 'label' => 'purchase_notification.customer_email', 'detail' => null, 'category' => 'other'],
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
