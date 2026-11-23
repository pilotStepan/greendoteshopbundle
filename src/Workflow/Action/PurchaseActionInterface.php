<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * An action a shop attaches to a purchase_flow transition in its workflow config:
 *
 *     log_prepare_to_ship:
 *         metadata:
 *             actions: [invoice.issue, { action: parcel.create, skippable: true }]
 *
 * Register the key with #[AsTaggedItem(index: 'invoice.issue')].
 * Actions run on `workflow.purchase_flow.completed`, inside the transition's transaction, in config order.
 */
#[AutoconfigureTag(self::TAG)]
interface PurchaseActionInterface
{
    public const TAG = 'greendot_eshop.purchase_action';

    /**
     * @return ActionDescription|null null when the action does nothing for this purchase
     */
    public function describe(Purchase $purchase, string $transition): ?ActionDescription;

    /**
     * @param array<string, mixed> $context the workflow context passed to apply()
     */
    public function execute(Purchase $purchase, string $transition, array $context): void;
}
