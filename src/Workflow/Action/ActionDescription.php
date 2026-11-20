<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

/**
 * What one action or system effect will do, as shown to the admin before a transition is applied.
 */
final readonly class ActionDescription
{
    /**
     * @param string      $label  Czech source text; translated in the `workflow_actions` domain
     * @param string|null $detail Specifics for this purchase, e.g. the recipient address
     */
    public function __construct(
        public string         $label,
        public ?string        $detail = null,
        public ActionCategory $category = ActionCategory::OTHER,
    ) {}
}
