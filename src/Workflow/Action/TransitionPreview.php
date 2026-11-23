<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

/**
 * What applying a transition to a purchase would do, for the CMS confirmation dialog.
 */
final readonly class TransitionPreview implements \JsonSerializable
{
    /**
     * @param list<string>                                                                                    $blockers
     * @param list<array{key: string, label: string, detail: ?string, category: string, skippable: bool, email_preview: bool, sms: array{text: string}|array{error: string}|null}> $actions
     * @param list<array{label: string, detail: ?string, category: string}>                                 $system
     */
    public function __construct(
        public string $transition,
        public string $label,
        public bool   $can,
        public array  $blockers,
        public array  $actions,
        public array  $system,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'transition' => $this->transition,
            'label' => $this->label,
            'can' => $this->can,
            'blockers' => $this->blockers,
            'actions' => $this->actions,
            'system' => $this->system,
        ];
    }
}
