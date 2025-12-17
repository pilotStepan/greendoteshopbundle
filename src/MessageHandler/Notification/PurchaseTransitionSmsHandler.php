<?php

namespace Greendot\EshopBundle\MessageHandler\Notification;

use Exception;
use Greendot\EshopBundle\Sms\ManageSms;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionSms;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
#[WithMonologChannel('messenger')]
readonly class PurchaseTransitionSmsHandler
{
    public function __construct(
        private ManageSms          $manageSms,
        private PurchaseRepository $purchaseRepository,
    ) {}

    public function __invoke(PurchaseTransitionSms $msg): void
    {
        $purchaseId = $msg->purchaseId;
        $purchase = $this->purchaseRepository->find($purchaseId);

        if (!$purchase) {
            throw new UnrecoverableMessageHandlingException('Purchase not found for ID: ' . $purchaseId);
        }

        try {
            $this->manageSms->sendOrderTransitionSms($purchase, $msg->transition);
        } catch (Exception $e) {
            throw new RecoverableMessageHandlingException('Failed to send SMS, retrying ' . $purchaseId, 0);
        }
    }
}