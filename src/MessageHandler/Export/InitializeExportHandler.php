<?php

namespace Greendot\EshopBundle\MessageHandler\Export;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Export;
use Greendot\EshopBundle\Entity\Project\ExportStatus;
use Greendot\EshopBundle\Export\ExportRegistry;
use Greendot\EshopBundle\Message\Export\InitializeExportMessage;
use Greendot\EshopBundle\Message\Export\ProcessChunkMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class InitializeExportHandler
{
    public function __construct(
        private readonly ExportRegistry         $registry,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface    $messageBus
    )
    {
    }

    public function __invoke(InitializeExportMessage $initializeExportMessage): void
    {
        $export = $this->entityManager->getRepository(Export::class)->find($initializeExportMessage->exportId);
        if (!$export || !$export->getExportStatus()) return;

        $type = $this->registry->get($initializeExportMessage->alias);
        $totalItems = $type->getTotalCount();

        $totalChunks = (int)ceil($totalItems / $initializeExportMessage->chunkSize);

        $status = $export->getExportStatus();
        $status->setStatus(ExportStatus::PROCESSING);
        $status->setTotalMessages($totalChunks);
        $status->setSuccessCount(0);
        $status->setFailedCount(0);
        $this->entityManager->flush();

        for ($i = 0; $i < $totalChunks; $i++){
            $offset = $i * $initializeExportMessage->chunkSize;
            $this->messageBus->dispatch(new ProcessChunkMessage(
                    exportId: $initializeExportMessage->exportId,
                    alias: $initializeExportMessage->alias,
                    offset: $offset,
                    limit: $initializeExportMessage->chunkSize
                ));
        }
    }
}