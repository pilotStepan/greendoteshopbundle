<?php

namespace Greendot\EshopBundle\MessageHandler\Watchdog\VariantAvailable;

use Throwable;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Watchdog;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\VariantAvailableEmail;

#[AsMessageHandler]
final readonly class VariantAvailableEmailHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ManageMails            $manageMails,
        private LoggerInterface        $logger,
    ) {}

    /**
     * @throws OptimisticLockException
     * @throws Throwable
     * @throws TransportExceptionInterface
     * @throws ORMException
     */
    public function __invoke(VariantAvailableEmail $message): void
    {
        /** @var Watchdog|null $watchdog */
        $watchdog = $this->em->find(Watchdog::class, $message->watchdogId);
        if ($watchdog === null) {
            throw new UnrecoverableMessageHandlingException('Watchdog not found for ID: ' . $message->watchdogId);
        }

        /** @var ProductVariant|null $variant */
        $variant = $this->em->find(ProductVariant::class, $message->productVariantId);
        if ($variant === null) {
            throw new UnrecoverableMessageHandlingException('ProductVariant not found for ID: ' . $message->productVariantId);
        }

        // Idempotency/duplicate delivery protection.
        if ($watchdog->getLastSentEventKey() === $message->eventKey) {
            return;
        }

        try {
            $email = $this->manageMails
                ->getBaseTemplate()
                ->to($message->email)
                ->htmlTemplate('/email/watchdog/variant-available.html.twig')
                ->context(['data' => [
                    'variant_name' => $variant->getName(),
                    'product_name' => $variant->getProduct()->getName(),
                    'product_slug' => $variant->getProduct()->getSlug(),
                ]])
                ->subject('Nová varianta produktu je dostupná')
            ;

            $this->manageMails->sendTemplate($email);

            $watchdog->markSent($message->eventKey);
            $this->em->flush();

            $this->logger->info('Variant available watchdog email sent.', [
                'watchdogId' => $watchdog->getId(),
                'variantId' => $variant->getId(),
                'email' => $message->email,
            ]);
        } catch (Throwable $e) {
            $watchdog->markFailed($message->eventKey, $e->getMessage());
            $this->em->flush();
            throw $e;
        }
    }
}
