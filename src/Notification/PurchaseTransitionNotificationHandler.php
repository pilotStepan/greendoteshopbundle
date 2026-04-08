<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class PurchaseTransitionNotificationHandler
{
    public function __construct(
        private PurchaseRepository $purchaseRepository,
        #[AutowireLocator('greendot_eshop.purchase_notification')]
        private ContainerInterface $locator,
        private LoggerInterface    $logger,
    ) {}

    public function __invoke(PurchaseTransitionNotification $msg): void
    {
        $purchase = $this->purchaseRepository->find($msg->purchaseId);

        if (!$purchase) {
            throw new UnrecoverableMessageHandlingException('Purchase not found for ID: ' . $msg->purchaseId);
        }

        if (!$this->locator->has($msg->alias)) {
            $this->logger->warning('No purchase notification handler found for alias', [
                'alias' => $msg->alias,
                'transition' => $msg->transition,
                'purchase' => $purchase->getId(),
            ]);
            return;
        }

        $purchaseNotificationHandler = $this->locator->get($msg->alias);

        if (!$purchaseNotificationHandler instanceof PurchaseNotificationHandlerInterface) {
            throw new UnrecoverableMessageHandlingException(sprintf(
                'Service for alias "%s" must implement PurchaseNotificationHandlerInterface.',
                $msg->alias,
            ));
        }

        $purchaseNotificationHandler->handle($purchase, $msg->transition);
    }
}
