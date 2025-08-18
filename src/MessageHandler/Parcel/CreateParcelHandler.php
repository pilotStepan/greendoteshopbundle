<?php

namespace Greendot\EshopBundle\MessageHandler\Parcel;

use Throwable;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Service\ManagePurchase;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Message\Parcel\CreateParcelMessage;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Greendot\EshopBundle\Service\Parcel\ParcelServiceProvider;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Message\Parcel\UpdateDeliveryStatusMessage;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
#[WithMonologChannel('messenger')]
readonly class CreateParcelHandler
{
    private const INITIAL_STATUS_DELAY_MS = 8 * 60 * 60 * 1000; // 8h

    public function __construct(
        private ParcelServiceProvider  $parcelServiceProvider,
        private PurchaseRepository     $purchaseRepository,
        private ManagePurchase         $managePurchase,
        private EntityManagerInterface $em,
        private MessageBusInterface    $bus,
        private LoggerInterface        $logger,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(CreateParcelMessage $msg): void
    {
        $purchaseId = $msg->purchaseId;
        $purchase = $this->purchaseRepository->find($purchaseId);

        if (!$purchase) {
            // Permanent: do not retry
            $this->logger->error('Purchase not found', ['purchaseId' => $purchaseId]);
            throw new UnrecoverableMessageHandlingException("Purchase not found (ID: $purchaseId)");
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

        // Resolve service
        $parcelService = $this->parcelServiceProvider->getByPurchase($purchase);
        if (!$parcelService) {
            $this->logger->error('No parcel service available', ['purchaseId' => $purchaseId]);
            throw new UnrecoverableMessageHandlingException("No parcel service available (Purchase ID: $purchaseId)");
        }

        // Create parcel
        try {
            $this->managePurchase->preparePrices($purchase);
            $parcelId = $parcelService->createParcel($purchase);
            $purchase->setTransportNumber($parcelId);
            $this->em->flush();
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
