<?php

namespace Greendot\EshopBundle\MessageHandler\Watchdog\VariantAvailable;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\WatchdogRepository;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\VariantAvailableEmail;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\NotifyVariantAvailable;

#[AsMessageHandler]
#[WithMonologChannel('watchdog.available')]
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
        $this->logger->debug('Processing NotifyVariantAvailable message.', [
            'productVariantId' => $message->productVariantId,
        ]);

        /** @var ProductVariant|null $variant */
        $variant = $this->em->find(ProductVariant::class, $message->productVariantId);
        if ($variant === null) {
            $this->logger->warning('Variant available watchdog: ProductVariant not found.', [
                'id' => $message->productVariantId,
            ]);
            return;
        }

        if (!self::shouldNotifyAvailable($variant)) {
            $this->logger->info('Product variant is not available => should not notify.', [
                'id' => $variant->getId(),
            ]);
            return;
        }

        $watchdogs = $this->watchdogRepository->findActiveVariantAvailableByVariantId($variant->getId());
        $changed = false;

        foreach ($watchdogs as $watchdog) {
            if ($watchdog->getQueuedAt() !== null || $watchdog->isCompleted()) {
                continue;
            }

            $this->messageBus->dispatch(new VariantAvailableEmail(
                watchdogId: (int)$watchdog->getId(),
                productVariantId: (int)$variant->getId(),
                email: $watchdog->getEmail(),
            ));

            $watchdog->markQueued();
            $changed = true;

            $this->logger->info('Variant available watchdog notification dispatched.', [
                'watchdogId' => $watchdog->getId(),
                'variantId' => $variant->getId(),
            ]);
        }

        if ($changed) {
            $this->em->flush();
        }
    }

    public static function shouldNotifyAvailable(ProductVariant $variant): bool
    {
        return $variant->getAvailability()?->getIsPurchasable() === true && $variant->getAvailability()?->getId() === 1;
    }
}