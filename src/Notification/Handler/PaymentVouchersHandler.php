<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification\Handler;

use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;

#[AsTaggedItem(index: 'purchase_notification.payment_vouchers')]
final readonly class PaymentVouchersHandler implements PurchaseNotificationHandlerInterface
{
    public function __construct(private ManageMails $manageMails) {}

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
