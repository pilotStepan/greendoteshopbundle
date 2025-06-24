<?php

namespace Greendot\EshopBundle\Command;

use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;


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
            ->addOption('clean', null, InputOption::VALUE_NONE, 'Overrides entire /src folder');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $bundlePath = $this->localBundlePath;
        $projectVendorPath = $this->getProjectRootPath() . '/vendor/greendot/eshopbundle';

        $clean = $input->getOption('clean');

        $io->info("Source path: $bundlePath");
        $io->info("Target path: $projectVendorPath");

        if (!is_dir($bundlePath)) {
            $io->error('Source directory not found');
            return Command::FAILURE;
        }

        if ($clean) {
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

        // Non-clean sync (incremental)
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
                ['git', 'ls-files', '--others', '--exclude-standard']
            ];

            foreach ($gitCommands as $command) {
                $process = new Process($command);
                $process->run();
                if ($process->isSuccessful()) {
                    $output = trim($process->getOutput());
                    if (!empty($output)) {
                        $changedFiles = array_merge($changedFiles, explode("\n", $output));
                    }
                }
            }

            return array_filter(array_unique($changedFiles));
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