<?php

namespace Greendot\EshopBundle\Parcel\MessageHandler;

use Throwable;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\TransportationEvent;
use Greendot\EshopBundle\Parcel\ParcelDeliveryStateEnum;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Parcel\Exception\ParcelServiceNotFoundException;
use Greendot\EshopBundle\Parcel\Exception\PermanentParcelException;
use Greendot\EshopBundle\Parcel\Message\CreateParcelMessage;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\TransportationEventRepository;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Parcel\Message\UpdateDeliveryStatusMessage;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
#[WithMonologChannel('messenger')]
readonly class CreateParcelHandler
{
    private const INITIAL_STATUS_DELAY_MS = 8 * 60 * 60 * 1000; // 8h

    public function __construct(
        private ParcelServiceProviderInterface $parcelServiceProvider,
        private PurchaseRepository             $purchaseRepository,
        private TransportationEventRepository  $transportationEventRepository,
        private EntityManagerInterface         $em,
        private MessageBusInterface            $bus,
        private LoggerInterface                $logger,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(CreateParcelMessage $msg): void
    {
        $purchaseId = $msg->purchaseId;
        $purchase = $this->purchaseRepository->find($purchaseId);

        if (!$purchase) {
            $this->logger->error('Purchase not found', ['purchaseId' => $purchaseId]);
            throw new UnrecoverableMessageHandlingException("Purchase not found (ID: $purchaseId)");
        }

        if ($purchase->hasAnyPlace(PWC::S_CANCELLED->value, PWC::S_COMPLETED->value)) {
            $this->logger->info('Purchase reached an end state; skipping parcel creation', ['purchaseId' => $purchaseId]);
            return;
        }

        // If a parcel already exists, skip creation but schedule status tracking
        if ($purchase->getTransportNumber()) {
            $this->logger->info('Parcel already exists; skipping create', [
                'purchaseId' => $purchaseId,
                'transportNumber' => $purchase->getTransportNumber(),
            ]);
            $this->scheduleFirstStatusCheck($purchaseId);
            return;
        }

        try {
            $parcelService = $this->parcelServiceProvider->getByPurchase($purchase);
        } catch (ParcelServiceNotFoundException $e) {
            $this->logger->error('No parcel service available', ['purchaseId' => $purchaseId]);
            throw new UnrecoverableMessageHandlingException("No parcel service available (Purchase ID: $purchaseId)", 0, $e);
        }

        try {
            $parcelId = $parcelService->createParcel($purchase);
            $purchase->setTransportNumber($parcelId);
            $this->recordReceivedDataEvent($purchase);
            $this->em->flush();
        } catch (PermanentParcelException $e) {
            $this->logger->error('Parcel creation rejected by carrier; manual action required', [
                'purchaseId' => $purchaseId,
                'error' => $e->getMessage(),
            ]);
            throw new UnrecoverableMessageHandlingException('Permanent parcel error (purchase ' . $purchaseId . ')', 0, $e);
        } catch (Throwable $e) {
            $this->logger->error('Creating parcel failed; will retry', [
                'purchaseId' => $purchaseId,
                'error' => $e::class . ': ' . $e->getMessage(),
            ]);
            throw new RecoverableMessageHandlingException('Transient error while creating parcel', 0, $e);
        }

        $this->logger->info('Parcel created', [
            'purchaseId' => $purchaseId,
            'transportNumber' => $purchase->getTransportNumber(),
        ]);

        $this->scheduleFirstStatusCheck($purchaseId);
    }

    private function recordReceivedDataEvent(Purchase $purchase): void
    {
        if ($this->transportationEventRepository->findLatestByPurchase($purchase)) {
            return;
        }

        $event = (new TransportationEvent())
            ->setState(ParcelDeliveryStateEnum::RECEIVED_DATA)
            ->setOccurredAt(new DateTimeImmutable())
            ->setPurchase($purchase)
            ->setTransportationAPI($purchase->getTransportation()->getTransportationAPI())
        ;

        $this->em->persist($event);
    }

    /**
     * @throws ExceptionInterface
     */
    private function scheduleFirstStatusCheck(int $purchaseId): void
    {
        $this->bus->dispatch(new UpdateDeliveryStatusMessage($purchaseId), [
            new DelayStamp(self::INITIAL_STATUS_DELAY_MS),
        ]);

        $this->logger->info('Scheduled initial status check', [
            'purchaseId' => $purchaseId,
            'delayMs' => self::INITIAL_STATUS_DELAY_MS,
        ]);
    }
}
