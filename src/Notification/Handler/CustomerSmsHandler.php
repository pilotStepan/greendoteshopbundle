<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification\Handler;

use Psr\Log\LoggerInterface;
use Neogate\SmsConnect\SmsConnect;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Greendot\EshopBundle\Notification\DescribableNotificationInterface;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Utils\PurchaseHelper;
use Greendot\EshopBundle\Notification\Sms\OrderTransitionSmsFactoryInterface;

#[AsTaggedItem(index: 'purchase_notification.customer_sms')]
#[WithMonologChannel('notification.sms')]
final readonly class CustomerSmsHandler implements PurchaseNotificationHandlerInterface, DescribableNotificationInterface
{
    public function __construct(
        private SmsConnect                         $client,
        private OrderTransitionSmsFactoryInterface $orderTransitionSmsFactory,
        private LoggerInterface                    $logger,
    ) {}

    public function describe(Purchase $purchase, string $transition): ActionDescription
    {
        return new ActionDescription(
            'Poslat SMS zákazníkovi',
            detail: PurchaseHelper::processPhone($purchase) ?: 'chybí telefon, neodešle se',
            category: ActionCategory::SMS,
        );
    }

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
            throw $e;
        }
    }
}
