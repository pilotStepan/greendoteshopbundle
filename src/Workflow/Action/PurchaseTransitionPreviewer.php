<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

use InvalidArgumentException;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\WorkflowInterface;
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
                ...$this->describe($a->description),
            ], $this->resolver->resolve($purchase, $this->purchaseWorkflow, $transition)),
            system: array_map(
                fn (ActionDescription $d) => $this->describe($d),
                $this->resolver->systemEffects($purchase, $this->purchaseWorkflow, $transition),
            ),
        );
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
