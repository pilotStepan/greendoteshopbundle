<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\MessageHandler\Notification;

use Greendot\EshopBundle\Message\Notification\PurchaseTransitionNotification;
use Greendot\EshopBundle\Notification\PurchaseNotificationDispatcher;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class PurchaseTransitionNotificationHandler
{
    public function __construct(
        private PurchaseNotificationDispatcher $dispatcher,
        private PurchaseRepository             $purchaseRepository,
    ) {}

    public function __invoke(PurchaseTransitionNotification $msg): void
    {
        $purchase = $this->purchaseRepository->find($msg->purchaseId);

        if (!$purchase) {
            throw new UnrecoverableMessageHandlingException('Purchase not found for ID: ' . $msg->purchaseId);
        }

        $this->dispatcher->dispatch($purchase, $msg->transition, $msg->handlerAliases);
    }
}
