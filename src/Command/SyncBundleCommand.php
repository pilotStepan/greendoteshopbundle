<?php

namespace Greendot\EshopBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
            throw new \RuntimeException('Define LOCAL_BUNDLE_PATH in your .env file to use this command');
        }
        $this->localBundlePath = $localBundlePath;
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->setHelp('Synchronizes changes from your local bundle development directory to the vendor directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $bundlePath = $this->localBundlePath;
        $projectVendorPath = $this->getProjectRootPath() . '/vendor/greendot/eshopbundle';

        $io->info("Source path: $bundlePath");
        $io->info("Target path: $projectVendorPath");

        if (!is_dir($bundlePath)) {
            $io->error('Source directory not found');
            return Command::FAILURE;
        }

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
        // Get composer.json location to determine project root
        $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
        return dirname($reflection->getFileName(), 3);
    }
}