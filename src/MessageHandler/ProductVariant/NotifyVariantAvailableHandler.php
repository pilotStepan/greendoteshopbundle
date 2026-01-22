<?php

namespace Greendot\EshopBundle\MessageHandler\ProductVariant;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Message\ProductVariant\NotifyVariantAvailable;

#[AsMessageHandler]
final readonly class NotifyVariantAvailableHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(NotifyVariantAvailable $message): void
    {
        $this->logger->info('Notified about ProductVariant ID {id} availability change.', [
            'id' => $message->productVariantId,
        ]);
    }
}