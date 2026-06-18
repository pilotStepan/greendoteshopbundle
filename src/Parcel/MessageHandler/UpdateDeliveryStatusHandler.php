<?php

namespace Greendot\EshopBundle\Parcel\MessageHandler;

use Throwable;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Parcel\ParcelServiceProvider;
use Greendot\EshopBundle\Parcel\ParcelDeliveryStateEnum;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Greendot\EshopBundle\Entity\Project\TransportationEvent;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Parcel\Message\UpdateDeliveryStatusMessage;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
#[WithMonologChannel('messenger')]
readonly class UpdateDeliveryStatusHandler
{
    private const MAX_POLL_DAYS = 14;
    private const REFRESH_INTERVAL = 4 * 60 * 60 * 1000; // 4h

    public function __construct(
        private ParcelServiceProvider  $parcelServiceProvider,
        private PurchaseRepository     $purchaseRepository,
        private EntityManagerInterface $em,
        private MessageBusInterface    $bus,
        private LoggerInterface        $logger,
        #[Target(PWC::NAME->value)]
        private WorkflowInterface              $purchaseFlow,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(UpdateDeliveryStatusMessage $msg): void
    {
        $purchaseId = $msg->purchaseId;
        $purchase = $this->purchaseRepository->find($purchaseId);

        if (!$purchase) {
            // Permanent: do not retry
            $this->logger->error('Purchase not found', ['purchaseId' => $purchaseId]);
            throw new UnrecoverableMessageHandlingException("Purchase not found (ID: $purchaseId)");
        }

        $lastEvent = $purchase->getLatestTransportationEvent();
        if ($lastEvent?->getState()->isFinal()) {
            $this->logger->info('Delivery final; skipping further checks', ['purchaseId' => $purchaseId]);
            return;
        }

        $createdAt = $purchase->getDateIssue();
        $ageDays = $createdAt->diff(new DateTimeImmutable())->days;
        if ($ageDays >= self::MAX_POLL_DAYS) {
            $this->logger->warning('Polling window expired; giving up', [
                'purchaseId' => $purchaseId,
                'ageDays' => $ageDays,
            ]);
            return;
        }

        try {
            $parcelService = $this->parcelServiceProvider->getByPurchase($purchase);
            $statusInfo = $parcelService->getParcelStatus($purchase);
        } catch (Throwable $e) {
            $this->logger->error('Parcel provider failed; will retry', [
                'purchaseId' => $purchaseId,
                'error' => $e::class . ': ' . $e->getMessage(),
            ]);
            throw new RecoverableMessageHandlingException('Transient parcel provider error', 0, $e);
        }

        // Skip writing if nothing changed
        if ($lastEvent &&
            $lastEvent->getState() === $statusInfo->state &&
            $lastEvent->getOccurredAt() === $statusInfo->occurredAt
        ) {
            $this->logger->info('No change in status; not persisting', ['purchaseId' => $purchaseId]);
        } else {
            $event = (new TransportationEvent())
                ->setState($statusInfo->state)
                ->setDetails($statusInfo->details)
                ->setOccurredAt($statusInfo->occurredAt)
                ->setPurchase($purchase)
            ;

            $this->em->persist($event);
            $this->em->flush();
        }

        $latest = $purchase->getLatestTransportationEvent(); // refresh latest event

        if ($latest?->getState() === ParcelDeliveryStateEnum::SUBMITTED) {
            $this->prepareForSending($purchase);
        }

        if ($latest?->getState()->isFinal()) {
            $this->logger->info('Status reached final; not rescheduling', ['purchaseId' => $purchaseId]);
            return;
        }

        $this->bus->dispatch(new UpdateDeliveryStatusMessage($purchaseId), [
            new DelayStamp(self::REFRESH_INTERVAL),
        ]);

        $this->logger->info('Rescheduled delivery status check', [
            'purchaseId' => $purchaseId,
            'delayMs' => self::REFRESH_INTERVAL,
        ]);
    }

    private function prepareForSending(Purchase $purchase): void
    {
        if ($this->purchaseFlow->can($purchase, 'prepare_for_sending')) {
            $this->purchaseFlow->apply($purchase, 'prepare_for_sending');
        } else {
            $errors = array_map(
                static fn($b) => $b->getMessage(),
                iterator_to_array($this->purchaseFlow->buildTransitionBlockerList($purchase, 'prepare_for_sending')),
            );
            $this->logger->error('Cannot apply transition to prepare for sending', [
                'purchaseId' => $purchase->getId(),
                'errors' => $errors,
            ]);
            throw new UnrecoverableMessageHandlingException("Cannot apply transition to prepare for sending (Purchase ID: {$purchase->getId()}");
        }
    }
}