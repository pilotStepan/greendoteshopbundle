<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification\Handler;

use Greendot\EshopBundle\Attribute\AsPurchaseNotification;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Message\Notification\IssuedVoucherEmail;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsPurchaseNotification('payment_vouchers')]
final readonly class PaymentVouchersHandler implements PurchaseNotificationHandlerInterface
{
    public function __construct(private MessageBusInterface $bus) {}

    public function handle(Purchase $purchase, string $transition): void
    {
        if ($purchase->getPaymentType()->getActionGroup() === PaymentTypeActionGroup::ON_DELIVERY) {
            return;
        }

        foreach ($purchase->getVouchersIssued() as $voucher) {
            $this->bus->dispatch(new IssuedVoucherEmail($voucher->getId()));
        }
    }
}
