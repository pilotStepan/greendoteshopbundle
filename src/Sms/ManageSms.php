<?php

namespace Greendot\EshopBundle\Sms;

use Exception;
use Throwable;
use Psr\Log\LoggerInterface;
use Neogate\SmsConnect\SmsConnect;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Sms\Factory\OrderTransitionSmsFactoryInterface;

#[WithMonologChannel('notification.sms')]
readonly class ManageSms
{
    public function __construct(
        private SmsConnect                         $client,
        private OrderTransitionSmsFactoryInterface $orderTransitionSmsFactory,
        private LoggerInterface                    $logger,
    ) {}

    /**
     * @throws Exception
     */
    public function sendOrderTransitionSms(Purchase $purchase, string $transition): void
    {
        try {
            $message = $this->orderTransitionSmsFactory->create($purchase, $transition);
            $this->client->sendSms($message->phone, $message->text, sender: $message->sender);
        } catch (Throwable $e) {
            $this->logger->critical('SMS sending failed', [
                'purchase' => $purchase->getId(),
                'transition' => $transition,
                'exception' => $e,
            ]);
            // throw $e;
        }
    }
}