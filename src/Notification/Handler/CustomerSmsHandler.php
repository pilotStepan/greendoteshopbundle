<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification\Handler;

use Psr\Log\LoggerInterface;
use Neogate\SmsConnect\SmsConnect;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Attribute\AsPurchaseNotification;
use Greendot\EshopBundle\Sms\Factory\OrderTransitionSmsFactoryInterface;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;

#[AsPurchaseNotification('customer_sms')]
#[WithMonologChannel('notification.sms')]
final readonly class CustomerSmsHandler implements PurchaseNotificationHandlerInterface
{
    public function __construct(
        private SmsConnect                         $client,
        private OrderTransitionSmsFactoryInterface $orderTransitionSmsFactory,
        private LoggerInterface                    $logger,
    ) {}

    public function handle(Purchase $purchase, string $transition): void
    {
        try {
            $message = $this->orderTransitionSmsFactory->create($purchase, $transition);
            $this->client->sendSms(
                $message->phone,
                $message->text,
                sender: $message->sender,
            );
        } catch (\Throwable $e) {
            $this->logger->critical('SMS sending failed', [
                'purchase' => $purchase->getId(),
                'transition' => $transition,
                'exception' => $e,
            ]);
            // throw $e;
        }
    }
}
