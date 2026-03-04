<?php

namespace Greendot\EshopBundle\MessageHandler\Export;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Export;
use Greendot\EshopBundle\Entity\Project\ExportStatus;
use Greendot\EshopBundle\Export\Contract\ExportTypeInterface;
use Greendot\EshopBundle\Export\ExportRegistry;
use Greendot\EshopBundle\Export\ExportStorageManager;
use Greendot\EshopBundle\Message\Export\AssembleExportMessage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AssembleExportHandler
{
    public function __construct(
        private readonly ExportRegistry         $exportRegistry,
        private readonly ExportStorageManager   $exportStorageManager,
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    public function __invoke(AssembleExportMessage $assembleExportMessage): void
    {
        $type = $this->exportRegistry->get($assembleExportMessage->alias);
        $currentExport = $this->entityManager->getRepository(Export::class)->find($assembleExportMessage->exportId);
        $hasFailed = $currentExport->getExportStatus()->getFailedCount() > 0;

        if (!$hasFailed){
            $this->handleArchive($type, $assembleExportMessage);
            $finalFilePath = $this->exportStorageManager->getFinalFilePath($assembleExportMessage->alias, $type->getFileExtension());
        }else{
            $finalFilePath = $this->exportStorageManager->getFailedFilePath($assembleExportMessage->exportId, $type->getFileExtension());
        }

        $tempDir = $this->exportStorageManager->getTempDir($assembleExportMessage->exportId);

        $fileHandle = fopen($finalFilePath, 'w');

        fwrite($fileHandle, $type->generateStartFile());

        $finder = new Finder();
        $finder->files()->in($tempDir)->name('chunk_*.tmp')->sortByName();

        foreach ($finder as $file) {
            $chunkHandle = fopen($file->getRealPath(), 'r');
            stream_copy_to_stream($chunkHandle, $fileHandle);
            fclose($chunkHandle);
        }

        fwrite($fileHandle, $type->generateEndFile());
        fclose($fileHandle);

        $this->exportStorageManager->cleanupTempDir($assembleExportMessage->exportId);

        $export = $this->entityManager->getRepository(Export::class)->find($assembleExportMessage->exportId);
        if ($export && $export->getExportStatus()) {
            if ($hasFailed){
                $export->getExportStatus()->setStatus(ExportStatus::FAILED);
            }else{
                $export->getExportStatus()->setStatus(ExportStatus::FINISHED);
            }
            $export->setFilename(basename($finalFilePath));
            $this->entityManager->flush();
        }
    }

    private function handleArchive(ExportTypeInterface $type, AssembleExportMessage $assembleExportMessage): void
    {
        $extension = $type->getFileExtension();
        $standardFilename = $assembleExportMessage->alias. $extension;

        $archiveRelativePath = $this->exportStorageManager->archiveExistingFile($assembleExportMessage->alias, $extension);
        if ($archiveRelativePath){
            $oldExport = $this->entityManager->getRepository(Export::class)->findOneBy([
                'filename' => $standardFilename
            ]);

            if ($oldExport){
                $oldExport->setFilename($archiveRelativePath);
            }
        }
    }
}