<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use Psr\Log\LoggerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;

final readonly class PurchaseNotificationDispatcher
{
    /**
     * @param array<string, PurchaseNotificationHandlerInterface> $handlers keyed by alias
     */
    public function __construct(
        private array           $handlers,
        private LoggerInterface $logger,
    ) {}

    public function dispatch(Purchase $purchase, string $transition, string $alias): void
    {
        $handler = $this->handlers[$alias] ?? null;

        if ($handler === null) {
            $this->logger->warning('No purchase notification handler found for alias', [
                'alias' => $alias,
                'transition' => $transition,
                'purchase' => $purchase->getId(),
            ]);
            return;
        }

        $handler->handle($purchase, $transition);
    }
}
