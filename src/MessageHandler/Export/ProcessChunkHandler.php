<?php

namespace Greendot\EshopBundle\MessageHandler\Export;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Export\ExportRegistry;
use Greendot\EshopBundle\Export\ExportStorageManager;
use Greendot\EshopBundle\Message\Export\AssembleExportMessage;
use Greendot\EshopBundle\Message\Export\ProcessChunkMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ProcessChunkHandler
{
    public function __construct(
        private readonly ExportRegistry $registry,
        private readonly ExportStorageManager $exportStorageManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus
    )
    {
    }

    public function __invoke(ProcessChunkMessage $processChunkMessage)
    {
        $type = $this->registry->get($processChunkMessage->alias);
        $items = $type->getItems($processChunkMessage->offset, $processChunkMessage->limit);

        $filePath = $this->exportStorageManager->getChunkFilePath($processChunkMessage->exportId, $processChunkMessage->offset);

        $handle = fopen($filePath, 'w');

        $failed = false;
        foreach ($items as $item){
            try {
                $generatedItem = $type->generateItem($item);
            }catch (\Exception $exception){
                $failed = true;
                break;
            }

            fwrite($handle, $generatedItem);
        }
        fclose($handle);

        $connection = $this->entityManager->getConnection();

        $updateSQL = sprintf(
            'UPDATE export_status SET %s = %s +1 WHERE export_id = :export_id',
            $failed ? 'failed_count' : 'success_count',
            $failed ? 'failed_count' : 'success_count',
        );
        $connection->executeStatement(
            $updateSQL,
            ['export_id' => $processChunkMessage->exportId]
        );

        $statusData = $connection->fetchAssociative(
            'SELECT success_count, failed_count, total_messages FROM export_status WHERE export_id = :export_id',
            ['export_id' => $processChunkMessage->exportId]
        );
        $totalProcessed = $statusData['success_count'] + $statusData['failed_count'];

        if ($totalProcessed >= $statusData['total_messages']){
            $this->messageBus->dispatch(new AssembleExportMessage(
                exportId: $processChunkMessage->exportId,
                alias: $processChunkMessage->alias
            ));
        }
    }
}