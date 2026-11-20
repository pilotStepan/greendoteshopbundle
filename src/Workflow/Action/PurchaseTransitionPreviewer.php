<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

use InvalidArgumentException;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Notification\PreviewableEmailNotificationInterface;
use Greendot\EshopBundle\Notification\PreviewableSmsNotificationInterface;
use Greendot\EshopBundle\Notification\PurchaseNotificationRuntime;
use Symfony\Component\Workflow\Transition;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class PurchaseTransitionPreviewer
{
    public const TRANSLATION_DOMAIN = 'workflow_actions';

    public function __construct(
        #[Target('purchase_flow')]
        private WorkflowInterface                $purchaseWorkflow,
        private PurchaseTransitionActionResolver $resolver,
        private TranslatorInterface              $translator,
        private PurchaseNotificationRuntime      $notificationRuntime,
    ) {}

    /**
     * @throws InvalidArgumentException when the workflow has no transition of that name
     */
    public function preview(Purchase $purchase, string $transitionName): TransitionPreview
    {
        $transition = $this->resolver->findTransition($purchase, $this->purchaseWorkflow, $transitionName);
        $can = $this->purchaseWorkflow->can($purchase, $transitionName);

        $blockers = [];
        if (!$can) {
            foreach ($this->purchaseWorkflow->buildTransitionBlockerList($purchase, $transitionName) as $blocker) {
                $blockers[] = $blocker->getMessage();
            }
        }

        $label = $this->purchaseWorkflow->getMetadataStore()->getMetadata('label', $transition);

        return new TransitionPreview(
            transition: $transitionName,
            label: is_string($label) ? $label : $transitionName,
            can: $can,
            blockers: $blockers,
            actions: array_map(fn (ResolvedAction $a) => [
                'key' => $a->entry->key,
                'skippable' => $a->entry->skippable,
                'email_preview' => $a->entry->kind === ActionKind::NOTIFICATION
                    && $this->resolver->notificationHandler($a->entry->key) instanceof PreviewableEmailNotificationInterface,
                'sms' => $this->smsPreview($purchase, $transition, $a),
                ...$this->describe($a->description),
            ], $this->resolver->resolve($purchase, $this->purchaseWorkflow, $transition)),
            system: array_map(
                fn (ActionDescription $d) => $this->describe($d),
                $this->resolver->systemEffects($purchase, $this->purchaseWorkflow, $transition),
            ),
        );
    }

    /**
     * The SMS text, rendered like the real one (purchase locale) from the purchase before the transition.
     *
     * @return array{text: string}|array{error: string}|null null when the action is no SMS or sends nothing
     */
    private function smsPreview(Purchase $purchase, Transition $transition, ResolvedAction $action): ?array
    {
        if ($action->entry->kind !== ActionKind::NOTIFICATION) {
            return null;
        }

        $handler = $this->resolver->notificationHandler($action->entry->key);
        if (!$handler instanceof PreviewableSmsNotificationInterface) {
            return null;
        }

        $notificationKey = $this->resolver->notificationKey($this->purchaseWorkflow, $transition);
        try {
            $sms = $this->notificationRuntime->run($purchase, fn () => $handler->buildSms($purchase, $notificationKey));
        } catch (\Throwable $e) {
            // The SMS factories log their own failures; the dialog only needs to say the text is unavailable.
            return ['error' => 'Text SMS nelze sestavit: ' . $e->getMessage()];
        }

        return $sms !== null ? ['text' => $sms->text] : null;
    }

    /**
     * @return array{label: string, detail: ?string, category: string}
     */
    private function describe(ActionDescription $description): array
    {
        return [
            'label' => $this->translator->trans($description->label, [], self::TRANSLATION_DOMAIN),
            'detail' => $description->detail,
            'category' => $description->category->value,
        ];
    }
}
