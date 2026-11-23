<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification\Handler;

use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Greendot\EshopBundle\Notification\DescribableNotificationInterface;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;

#[AsTaggedItem(index: 'purchase_notification.payment_vouchers')]
final readonly class PaymentVouchersHandler implements PurchaseNotificationHandlerInterface, DescribableNotificationInterface
{
    public function __construct(private ManageMails $manageMails) {}

    public function describe(Purchase $purchase, string $transition): ?ActionDescription
    {
        $count = $purchase->getVouchersIssued()->count();
        if ($count === 0 || $purchase->getPaymentType()?->getActionGroup() === PaymentTypeActionGroup::ON_DELIVERY) {
            return null;
        }

        return new ActionDescription('Poslat e-mail s poukazy', detail: sprintf('%d ks', $count), category: ActionCategory::EMAIL);
    }

    public function handle(Purchase $purchase, string $transition): void
    {
        if ($purchase->getPaymentType()->getActionGroup() === PaymentTypeActionGroup::ON_DELIVERY) {
            return;
        }

        foreach ($purchase->getVouchersIssued() as $voucher) {
            $this->manageMails->sendIssuedVoucherEmail($voucher);
        }
    }
}
