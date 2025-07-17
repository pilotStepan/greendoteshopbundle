<?php

namespace Greendot\EshopBundle\MessageHandler\Notification;

use Greendot\EshopBundle\Service\ManageSms;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionSms;

#[AsMessageHandler]
readonly class PurchaseTransitionSmsHandler
{
    public function __construct(
        private ManageSms          $manageSms,
        private PurchaseRepository $purchaseRepository,
    ) {}

    public function __invoke(PurchaseTransitionSms $msg): void
    {
        $purchaseId = $msg->getPurchaseId();
        $purchase = $this->purchaseRepository->find($purchaseId);

        if (!$purchase) {
            throw new \RuntimeException('Purchase not found for ID: ' . $purchaseId);
        }

        $this->manageSms->sendOrderTransitionSms($purchase, $msg->getTransition());
    }
}