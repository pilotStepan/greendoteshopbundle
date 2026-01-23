<?php

namespace Greendot\EshopBundle\MessageHandler\Watchdog\VariantAvailable;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\WatchdogRepository;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\VariantAvailableEmail;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\NotifyVariantAvailable;

#[AsMessageHandler]
final readonly class NotifyVariantAvailableHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface        $logger,
        private WatchdogRepository     $watchdogRepository,
        private MessageBusInterface    $messageBus,
    ) {}

    public function __invoke(NotifyVariantAvailable $message): void
    {
        /** @var ProductVariant|null $variant */
        $variant = $this->em->find(ProductVariant::class, $message->productVariantId);
        if ($variant === null) {
            $this->logger->warning('Variant available watchdog: ProductVariant not found.', [
                'id' => $message->productVariantId,
            ]);
            return;
        }

        // Defensive: endpoint should only be called when availability switches to available.
        if ($variant->getAvailability()?->getIsPurchasable() !== true || $variant->getAvailability()?->getId() !== 1) {
            return;
        }

        $eventKey = sprintf('variant:%d:availability:%d', $variant->getId(), $variant->getAvailability()?->getId() ?? 0);

        $watchdogs = $this->watchdogRepository->findActiveVariantAvailableByVariantId($variant->getId());
        foreach ($watchdogs as $watchdog) {
            if (!$watchdog->shouldQueueEvent($eventKey)) {
                continue;
            }

            try {
                $this->messageBus->dispatch(new VariantAvailableEmail(
                    watchdogId: (int)$watchdog->getId(),
                    productVariantId: (int)$variant->getId(),
                    email: $watchdog->getEmail(),
                    eventKey: $eventKey,
                ));

                $watchdog->markQueued($eventKey);

                $this->logger->info('Variant available watchdog notification dispatched.', [
                    'watchdogId' => $watchdog->getId(),
                    'variantId' => $variant->getId(),
                ]);
            } catch (\Throwable $e) {
                $watchdog->markFailed($eventKey, $e->getMessage());

                $this->logger->error('Variant available watchdog notification dispatch failed.', [
                    'watchdogId' => $watchdog->getId(),
                    'variantId' => $variant->getId(),
                    'exception' => $e,
                ]);
            }

            $this->em->flush();
        }
    }
}