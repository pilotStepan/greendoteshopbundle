<?php

namespace Greendot\EshopBundle\MessageHandler\Notification;

use Greendot\EshopBundle\Service\ManageMails;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Message\Notification\PurchaseTransitionEmail;

#[AsMessageHandler]
readonly class PurchaseTransitionEmailHandler
{
    public function __construct(
        private ManageMails        $manageMails,
        private PurchaseRepository $purchaseRepository,
    ) {}

    public function __invoke(PurchaseTransitionEmail $msg): void
    {
        $purchaseId = $msg->getPurchaseId();
        $purchase = $this->purchaseRepository->find($purchaseId);

        if (!$purchase) {
            throw new \RuntimeException('Purchase not found for ID: ' . $purchaseId);
        }

        $this->manageMails->sendPurchaseTransitionEmail($purchase, $msg->getTransition());
    }
}