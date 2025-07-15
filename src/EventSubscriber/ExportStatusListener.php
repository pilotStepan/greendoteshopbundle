<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Export;
use Greendot\EshopBundle\Entity\Project\ExportStatus;
use Greendot\EshopBundle\Repository\Project\ExportStatusRepository;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

use Greendot\EshopBundle\Message\Export as ExportMessage;


#[AsEventListener(event: WorkerMessageFailedEvent::class, method: 'onMessageFailed')]
#[AsEventListener(event: WorkerMessageReceivedEvent::class, method: 'onMessageReceived')]
#[AsEventListener(event: WorkerMessageHandledEvent::class, method: 'onMessageHandled')]
#[AsEventListener(event: SendMessageToTransportsEvent::class, method: 'onSendToTransport')]
#[AsEntityListener(event: Events::postPersist, method: 'createExportStatus', entity: Export::class)]
final class ExportStatusListener
{
    private LockFactory $lockFactory;
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExportStatusRepository $exportStatusRepository,
        private ServiceLocator $locator,
    )
    {
        $store = new FlockStore();
        $this->lockFactory = new LockFactory($store);
    }

    const EXPORT_STATUS_STARTED = 'started';
    const EXPORT_STATUS_PROCESSING = 'processing';
    const EXPORT_STATUS_FINISHED = 'finished';
    const EXPORT_STATUS_FAILED = 'failed';

    public function createExportStatus(Export $export): void
    {
        $exportStatus = new ExportStatus();
        $exportStatus->setExport($export);
        $exportStatus->setSuccessCount(0);
        $exportStatus->setFailedCount(0);
        $exportStatus->setTotalMessages(0);
        $exportStatus->setStatus(self::EXPORT_STATUS_STARTED);
        $this->entityManager->persist($exportStatus);
        $this->entityManager->flush();
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $this->updateExportStatusCount($event, 'failedCount');
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        return;
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->updateExportStatusCount($event, 'successCount');
    }

    public function onSendToTransport(SendMessageToTransportsEvent $event): void
    {
        $message = $event->getEnvelope()?->getMessage();
        if (!$message instanceof ExportMessage){
            return;
        }
        $exportStatus = $this->getExportStatus($message);
        $exportStatus->setTotalMessages($exportStatus->getTotalMessages()+1);

        $this->entityManager->persist($exportStatus);
        $this->entityManager->flush();
    }

    private function getExportStatus(ExportMessage $message): ExportStatus
    {
        $exportStatus = $this->exportStatusRepository->findOneBy(['export' => $message->getExportId()]);
        if (!$exportStatus) {
            throw new \Exception('ExportStatus not found for ID: ' . $message->getExportId());
        }
        return $exportStatus;
    }

    private function updateExportStatusCount(WorkerMessageFailedEvent|WorkerMessageHandledEvent $event, string $field): void
    {
        $message = $event->getEnvelope()?->getMessage();
        if (!$message instanceof ExportMessage) return;

        $lock = $this->acquireLock($message->getExportId());

        $exportStatus = $this->getExportStatus($message);
        $currentCount = match ($field){
            'failedCount' => $exportStatus->getFailedCount() ?? 0,
            'successCount' => $exportStatus->getSuccessCount() ?? 0,
            default => throw new \InvalidArgumentException('Invalid export field: ' . $field),
        };

        $setter = 'set'.ucfirst($field);
        $exportStatus->$setter($currentCount+1);
        if ($exportStatus->getStatus() !== self::EXPORT_STATUS_PROCESSING){
            $exportStatus->setStatus(self::EXPORT_STATUS_PROCESSING);
        }

        if (($exportStatus->getSuccessCount() + $exportStatus->getFailedCount()) === $exportStatus->getTotalMessages()){
            if ($exportStatus->getSuccessCount() === $exportStatus->getTotalMessages()){
                $exportStatus->setStatus(self::EXPORT_STATUS_FINISHED);
                $this->onSuccessfullFinnish($event, $message);
            }else{
                $exportStatus->setStatus(self::EXPORT_STATUS_FAILED);
            }
        }
        $this->entityManager->persist($exportStatus);
        $this->entityManager->flush();
        $lock->release();
    }

    private function onSuccessfullFinnish(WorkerMessageHandledEvent $event, ExportMessage $message):void
    {
        $exportInstance = $this->locator->get($message->getExportClass());
        if ($message->getLocale()){
            $exportInstance->setLocale($message->getLocale());
        }
        if ($message->getCurrencyId()){
            $currency = $this->entityManager->getRepository(Currency::class)->find($message->getCurrencyId());
            $exportInstance->setCurrency($currency);
        }
        $exportInstance->endExport();
    }

    private function acquireLock(int $exportId): SharedLockInterface
    {
        $lock = $this->lockFactory->createLock('export_status_lock_'.$exportId);
        while (!$lock->acquire()){
            usleep(0.5*1000000);
        }
        return $lock;
    }

}