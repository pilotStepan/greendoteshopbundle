<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Implemented by every event subscriber that reacts to purchase_flow events in code (not through `actions` config),
 * so the CMS can show those built-in effects before a transition is applied. They cannot be skipped.
 */
#[AutoconfigureTag(self::TAG)]
interface PurchaseTransitionEffectDescriberInterface
{
    public const TAG = 'greendot_eshop.purchase_effect_describer';

    /**
     * @return iterable<ActionDescription>
     */
    public function describeEffects(Purchase $purchase, Transition $transition, WorkflowInterface $workflow): iterable;
}
