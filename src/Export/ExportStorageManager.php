<?php

namespace Greendot\EshopBundle\Export;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

class ExportStorageManager
{
    private string $baseTmpDir;
    private Filesystem $filesystem;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    )
    {
        $this->baseTmpDir = $projectDir.'/var/exports/tmp';
        $this->filesystem = new Filesystem();
    }

    public function getTempDir(int $exportId): string
    {
        $dir = sprintf('%s/%s', $this->baseTmpDir, $exportId);
        if (!$this->filesystem->exists($dir)){
            $this->filesystem->mkdir($dir);
        }
        return $dir;
    }

    public function getChunkFilePath(int $exportId, int $offset): string
    {
        return sprintf('%s/chunk_%010d.tmp', $this->getTempDir($exportId), $offset);
    }


    public function getFailedFilePath(int $exportId, string $extension): string
    {

        return sprintf('%s/%d%s', $this->getFailedExportsDir(), $exportId, $extension);
    }

    public function getFailedExportsDir(): string
    {
        $dir = sprintf('%s/public/exports/failed', $this->projectDir);
        if(!$this->filesystem->exists($dir)){
            $this->filesystem->mkdir($dir);
        }
        return $dir;
    }

    public function getFinalFilePath(string $alias, string $extension): string
    {
        return sprintf('%s/public/exports/%s%s', $this->projectDir, $alias, $extension);
    }

    public function getOldExportsDir(): string
    {
        $dir = sprintf('%s/public/exports/old', $this->projectDir);
        if (!$this->filesystem->exists($dir)){
            $this->filesystem->mkdir($dir);
        }
        return $dir;
    }

    public function archiveExistingFile(string $alias, string $extension): ?string
    {
        $currentPath = $this->getFinalFilePath($alias, $extension);

        if(!$this->filesystem->exists($currentPath)) return null;

        $this->getOldExportsDir();
        $timestamp = date('Ymd_His');
        $archiveFilename = sprintf('%s_%s%s', $alias, $timestamp, $extension);
        $archivePath = sprintf('%s/%s', $this->getOldExportsDir(), $archiveFilename);

        $this->filesystem->rename($currentPath, $archivePath);
        return 'old/'.$archiveFilename;
    }

    public function cleanupTempDir(int $exportId): void
    {
        $this->filesystem->remove($this->getTempDir($exportId));
    }
}