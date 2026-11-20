<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action\Builtin;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\PurchaseActionInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Assigns the next invoice number, unless the purchase already has one.
 */
#[AsTaggedItem(index: self::KEY)]
final readonly class IssueInvoiceAction implements PurchaseActionInterface
{
    public const KEY = 'invoice.issue';

    public function __construct(private ManagePurchase $managePurchase) {}

    public function describe(Purchase $purchase, string $transition): ?ActionDescription
    {
        if ($purchase->getInvoiceNumber() !== null) {
            return null;
        }

        return new ActionDescription('Vystavit fakturu', category: ActionCategory::DOCUMENT);
    }

    public function execute(Purchase $purchase, string $transition, array $context): void
    {
        $this->managePurchase->issueInvoice($purchase);
    }
}
