<?php

namespace Greendot\EshopBundle\Command;

use Composer\Autoload\ClassLoader;
use Greendot\EshopBundle\Service\BranchImport\Importer\BalikovnaImporter;
use Greendot\EshopBundle\Service\BranchImport\Importer\PostaImporter;
use Greendot\EshopBundle\Service\BranchImport\Importer\ZasilkovnaImporter;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'save:branches',
    description: 'Download and save provider branches XML into the project var/ directory',
)]
class SaveBranchesCommand extends Command
{
    public function __construct(
        private PostaImporter      $postaImporter,
        private BalikovnaImporter  $balikovnaImporter,
        private ZasilkovnaImporter $zasilkovnaImporter,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'provider',
                InputArgument::REQUIRED,
                'Provider key: posta | balikovna | zasilkovna',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $provider = strtolower((string)$input->getArgument('provider'));
        $importers = [
            'posta' => $this->postaImporter,
            'balikovna' => $this->balikovnaImporter,
            'zasilkovna' => $this->zasilkovnaImporter,
        ];

        if (!isset($importers[$provider])) {
            $output->writeln("<error>Unknown provider '{$provider}'. Use: posta | balikovna | zasilkovna</error>");
            return Command::FAILURE;
        }

        $filePath = $this->buildVarPath(sprintf('%s_branches.xml', $provider));

        $importer = $importers[$provider];
        $ok = $importer->downloadTo($filePath);

        if ($ok) {
            $real = realpath($filePath) ?: $filePath;
            $output->writeln("{$provider}: XML saved to {$real}");
            return Command::SUCCESS;
        }

        $output->writeln("<error>{$provider}: failed to save XML</error>");
        return Command::FAILURE;
    }

    private function buildVarPath(string $fileName): string
    {
        $root = $this->getProjectRootPath();
        $dir = $root . DIRECTORY_SEPARATOR . 'var';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $fileName;
    }

    private function getProjectRootPath(): string
    {
        $ref = new ReflectionClass(ClassLoader::class);
        return \dirname($ref->getFileName(), 3);
    }
}
