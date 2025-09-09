<?php

namespace Greendot\EshopBundle\MessageHandler\Notification;

use Greendot\EshopBundle\Service\ManageMails;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Message\Notification\PurchaseDiscussionEmail;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class PurchaseDiscussionEmailHandler
{
    public function __construct(
        private ManageMails        $manageMails,
        private PurchaseRepository $purchaseRepository,
    ) {}

    public function __invoke(PurchaseDiscussionEmail $msg): void
    {
        $purchaseId = $msg->purchaseId;
        $purchase = $this->purchaseRepository->find($purchaseId);

        if (!$purchase) {
            throw new UnrecoverableMessageHandlingException('Purchase not found for ID: ' . $purchaseId);
        }

        $this->manageMails->sendPurchaseDiscussionEmail($purchase);
    }
}