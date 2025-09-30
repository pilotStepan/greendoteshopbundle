<?php

namespace Greendot\EshopBundle\Command;

use ReflectionClass;
use RuntimeException;
use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Composer\Autoload\ClassLoader;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'greendot:eshop:sync',
    description: 'Sync changed bundle files to vendor directory',
)]
class SyncBundleCommand extends Command
{
    private string $localBundlePath;

    public function __construct(string $localBundlePath = null)
    {
        if ($localBundlePath === null) {
            throw new RuntimeException('Define LOCAL_BUNDLE_PATH in your .env file to use this command');
        }
        $this->localBundlePath = $localBundlePath;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Synchronizes changes from your local bundle development directory to the vendor directory')
            ->addOption('clean', null, InputOption::VALUE_NONE, 'Overrides entire /src folder')
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Keeps running and syncs on file changes (like yarn watch)')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Polling interval (seconds) when using --watch', '1')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $bundlePath = $this->localBundlePath;
        $projectVendorPath = $this->getProjectRootPath() . '/vendor/greendot/eshopbundle';

        $clean = (bool)$input->getOption('clean');
        $watch = (bool)$input->getOption('watch');
        $interval = max(0, (int)$input->getOption('interval'));

        $io->info("Source path: $bundlePath");
        $io->info("Target path: $projectVendorPath");

        if (!is_dir($bundlePath)) {
            $io->error('Source directory not found');
            return Command::FAILURE;
        }

        $result = $clean
            ? $this->syncClean($bundlePath, $projectVendorPath, $io, $filesystem)
            : $this->syncIncremental($bundlePath, $projectVendorPath, $io, $filesystem);
        if (!$watch) {
            return $result;
        }

        // WATCH MODE
        $io->info('Starting in watch mode. Press CTRL+C to stop.');

        // Initial signature to avoid immediate duplicate run
        $lastSignature = $this->getGitStatusSignature($bundlePath);

        while (true) {
            $currentSignature = $this->getGitStatusSignature($bundlePath);

            if ($currentSignature !== $lastSignature) {
                $io->section(date('H:i:s') . ' Change detected → syncing…');

                $result = $clean
                    ? $this->syncClean($bundlePath, $projectVendorPath, $io, $filesystem)
                    : $this->syncIncremental($bundlePath, $projectVendorPath, $io, $filesystem);

                if ($result === Command::FAILURE) {
                    // keep watching, just report the failure
                    $io->error('Incremental sync failed (watch continues).');
                }
                $lastSignature = $currentSignature;
            }

            if ($interval > 0) {
                sleep($interval);
            } else {
                // busy loop safeguard
                usleep(200_000);
            }
        }
    }

    /**
     * CLEAN SYNC (copy src/ fully, remove extras)
     */
    private function syncClean(string $bundlePath, string $projectVendorPath, SymfonyStyle $io, Filesystem $filesystem): int
    {
        $srcSourcePath = $bundlePath . '/src';
        $srcTargetPath = $projectVendorPath . '/src';
        $tmpTargetPath = $projectVendorPath . '/.sync_tmp_src';

        if (!is_dir($srcSourcePath)) {
            $io->error('Source src/ directory not found');
            return Command::FAILURE;
        }

        // Remove temp dir if exists
        if (is_dir($tmpTargetPath)) {
            $filesystem->remove($tmpTargetPath);
        }

        try {
            $changedFiles = $this->getAllFilesRelative($srcSourcePath);
            $io->info(sprintf('Clean sync: copying all %d files from src/ to temp', count($changedFiles)));

            // Copy all files to temp dir
            foreach ($changedFiles as $file) {
                $sourcePath = $srcSourcePath . '/' . $file;
                $targetPath = $tmpTargetPath . '/' . $file;
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    $filesystem->mkdir($targetDir);
                }
                $filesystem->copy($sourcePath, $targetPath, true);
            }

            // Remove extra files in temp dir (to match source exactly)
            $this->removeExtraFiles($tmpTargetPath, $srcSourcePath, $io, $filesystem);

            // Ensure vendor path exists
            if (!is_dir($projectVendorPath)) {
                $filesystem->mkdir($projectVendorPath);
            }

            // Remove old src (but keep .git etc. in parent)
            if (is_dir($srcTargetPath)) {
                $filesystem->remove($srcTargetPath);
            }

            // Move temp dir to src
            $filesystem->rename($tmpTargetPath, $srcTargetPath, true);

            $io->success(sprintf('Successfully synced %d files to vendor', count($changedFiles)));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            // Cleanup temp dir
            if (is_dir($tmpTargetPath)) {
                $filesystem->remove($tmpTargetPath);
            }
            $io->error('Sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * INCREMENTAL SYNC (uses git to detect changed files)
     */
    private function syncIncremental(string $bundlePath, string $projectVendorPath, SymfonyStyle $io, Filesystem $filesystem): int
    {
        $io->info('Getting changed files from Git...');
        $changedFiles = $this->getGitChangedFiles($bundlePath);
        if (empty($changedFiles)) {
            $io->info('No changes detected in the bundle.');
            return Command::SUCCESS;
        }
        $io->info(sprintf('Found %d changed files to sync', count($changedFiles)));

        if (!is_dir($projectVendorPath)) {
            $filesystem->mkdir($projectVendorPath);
        }

        $count = 0;
        foreach ($changedFiles as $file) {
            $sourcePath = $bundlePath . DIRECTORY_SEPARATOR . $file;
            $targetPath = $projectVendorPath . DIRECTORY_SEPARATOR . $file;

            if (!file_exists($sourcePath)) {
                $io->warning("Skipping $file - file doesn't exist");
                continue;
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                $filesystem->mkdir($targetDir);
            }

            $filesystem->copy($sourcePath, $targetPath, true);
            $io->text("Copied: $file");
            $count++;
        }

        $io->success(sprintf('Successfully copied %d files to vendor', $count));
        return Command::SUCCESS;
    }

    private function getAllFilesRelative(string $basePath): array
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($rii as $file) {
            if ($file->isFile()) {
                $files[] = ltrim(str_replace($basePath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            }
        }
        return $files;
    }

    private function removeExtraFiles(string $target, string $source, SymfonyStyle $io, Filesystem $filesystem): void
    {
        $sourceFiles = $this->getAllFilesRelative($source);
        $targetFiles = $this->getAllFilesRelative($target);

        $extraFiles = array_diff($targetFiles, $sourceFiles);
        foreach ($extraFiles as $file) {
            $filePath = $target . DIRECTORY_SEPARATOR . $file;
            $filesystem->remove($filePath);
            $io->text("Removed extra file: $file");
        }
    }

    private function getGitChangedFiles(string $repoPath): array
    {
        $changedFiles = [];
        $currentDir = getcwd();

        try {
            chdir($repoPath);

            $gitCommands = [
                ['git', 'diff', '--name-only', '--cached'],
                ['git', 'diff', '--name-only'],
                ['git', 'ls-files', '--others', '--exclude-standard'],
            ];

            foreach ($gitCommands as $command) {
                $process = new Process($command);
                $process->run();
                if ($process->isSuccessful()) {
                    $output = trim($process->getOutput());
                    if ($output !== '') {
                        $changedFiles = array_merge($changedFiles, explode("\n", $output));
                    }
                }
            }

            return array_values(array_filter(array_unique($changedFiles)));
        } finally {
            chdir($currentDir);
        }
    }

    private function getGitStatusSignature(string $repoPath): string
    {
        $currentDir = getcwd();
        try {
            chdir($repoPath);

            $process = new Process(['git', 'status', '--porcelain=1', '--untracked-files=normal']);
            $process->run();

            $out = $process->isSuccessful() ? $process->getOutput() : '';
            return sha1($out);
        } finally {
            chdir($currentDir);
        }
    }

    private function getProjectRootPath(): string
    {
        $reflection = new ReflectionClass(ClassLoader::class);
        return dirname($reflection->getFileName(), 3);
    }
}