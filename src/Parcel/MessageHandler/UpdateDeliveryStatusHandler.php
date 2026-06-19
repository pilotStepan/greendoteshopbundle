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
use Greendot\EshopBundle\Parcel\ParcelDeliveryStateEnum;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Greendot\EshopBundle\Entity\Project\TransportationEvent;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Parcel\Message\UpdateDeliveryStatusMessage;
use Greendot\EshopBundle\Parcel\Exception\ParcelServiceNotFoundException;
use Greendot\EshopBundle\Repository\Project\TransportationEventRepository;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
#[WithMonologChannel('messenger')]
readonly class UpdateDeliveryStatusHandler
{
    private const MAX_POLL_DAYS = 14;
    private const REFRESH_INTERVAL = 1 * 60 * 60 * 1000; // 1h

    public function __construct(
        private ParcelServiceProviderInterface $parcelServiceProvider,
        private PurchaseRepository             $purchaseRepository,
        private TransportationEventRepository  $transportationEventRepository,
        private EntityManagerInterface         $em,
        private MessageBusInterface            $bus,
        private LoggerInterface                $logger,
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
            $this->logger->error('Purchase not found', ['purchaseId' => $purchaseId]);
            throw new UnrecoverableMessageHandlingException("Purchase not found (ID: $purchaseId)");
        }

        $lastEvent = $this->transportationEventRepository->findLatestByPurchase($purchase);
        if ($lastEvent?->getState()->isFinal()) {
            $this->logger->info('Delivery final; skipping further checks', ['purchaseId' => $purchaseId]);
            return;
        }

        $ageDays = $purchase->getDateIssue()->diff(new DateTimeImmutable())->days;
        if ($ageDays >= self::MAX_POLL_DAYS) {
            $this->logger->warning('Polling window expired; giving up', [
                'purchaseId' => $purchaseId,
                'ageDays' => $ageDays,
            ]);
            return;
        }

        try {
            $parcelService = $this->parcelServiceProvider->getByPurchase($purchase);
        } catch (ParcelServiceNotFoundException $e) {
            $this->logger->error('No parcel service found; stopping polling', ['purchaseId' => $purchaseId]);
            throw new UnrecoverableMessageHandlingException('No parcel service available', 0, $e);
        }

        try {
            $statusInfo = $parcelService->getParcelStatus($purchase);
        } catch (Throwable $e) {
            $this->logger->error('Parcel provider failed; will retry', [
                'purchaseId' => $purchaseId,
                'error' => $e::class . ': ' . $e->getMessage(),
            ]);
            throw new RecoverableMessageHandlingException('Transient parcel provider error', 0, $e);
        }

        // Skip writing if nothing changed (use == for value equality on DateTimeInterface)
        if ($lastEvent &&
            $lastEvent->getState() === $statusInfo->state &&
            $lastEvent->getOccurredAt() == $statusInfo->occurredAt
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

        if ($statusInfo->state === ParcelDeliveryStateEnum::IN_TRANSIT) {
            $this->applyTransitionIfPossible($purchase, PWC::T_LOG_SEND->value);
        } else if (in_array($statusInfo->state, [
            ParcelDeliveryStateEnum::DELIVERED,
            ParcelDeliveryStateEnum::READY_FOR_PICKUP,
        ])) {
            $this->applyTransitionIfPossible($purchase, PWC::T_LOG_DELIVER->value);
        }

        $this->em->flush();

        if ($statusInfo->state->isFinal()) {
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

    private function applyTransitionIfPossible(Purchase $purchase, string $transition): void
    {
        if ($this->purchaseFlow->can($purchase, $transition)) {
            $this->purchaseFlow->apply($purchase, $transition);
        } else {
            $errors = array_map(
                static fn($b) => $b->getMessage(),
                iterator_to_array($this->purchaseFlow->buildTransitionBlockerList($purchase, $transition)),
            );
            $this->logger->warning('Cannot apply workflow transition', [
                'purchaseId' => $purchase->getId(),
                'transition' => $transition,
                'errors' => $errors,
            ]);
        }
    }
}
