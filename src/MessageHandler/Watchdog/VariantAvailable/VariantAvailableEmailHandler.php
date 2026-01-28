<?php

namespace Greendot\EshopBundle\MessageHandler\Watchdog\VariantAvailable;

use Throwable;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Watchdog;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogState;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\VariantAvailableEmail;

#[AsMessageHandler]
#[WithMonologChannel('watchdog.available')]
final readonly class VariantAvailableEmailHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ManageMails            $manageMails,
        private LoggerInterface        $logger,
    ) {}

    /**
     * @throws OptimisticLockException|ORMException|Throwable
     */
    public function __invoke(VariantAvailableEmail $message): void
    {
        /** @var Watchdog|null $watchdog */
        $watchdog = $this->em->find(Watchdog::class, $message->watchdogId);
        if ($watchdog === null) {
            throw new UnrecoverableMessageHandlingException('Watchdog not found for ID: ' . $message->watchdogId);
        }

        if (in_array($watchdog->getState(), [WatchdogState::Completed, WatchdogState::Canceled], true)) {
            return;
        }

        /** @var ProductVariant|null $variant */
        $variant = $this->em->find(ProductVariant::class, $message->productVariantId);
        if ($variant === null) {
            throw new UnrecoverableMessageHandlingException('ProductVariant not found for ID: ' . $message->productVariantId);
        }

        $email = $this->manageMails
            ->getBaseTemplate()
            ->to($message->email)
            ->htmlTemplate('/email/watchdog/variant_available.html.twig')
            ->context(['data' => [
                'variant_name' => $variant->getName(),
                'product_name' => $variant->getProduct()->getName(),
                'product_slug' => $variant->getProduct()->getSlug(),
            ]])
            ->subject('Nová varianta produktu je dostupná')
        ;

        $this->manageMails->sendTemplate($email);

        $watchdog->markCompleted();
        $this->em->flush();

        $this->logger->info('Variant available watchdog email sent.', [
            'watchdogId' => $watchdog->getId(),
            'variantId' => $variant->getId(),
            'email' => $message->email,
        ]);
    }
}
