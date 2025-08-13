<?php

namespace Greendot\EshopBundle\Command;

use Throwable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Greendot\EshopBundle\Service\BranchImport\ManageBranch;

#[AsCommand(
    name: 'import:branches',
    description: 'Import branches from providers (posta|balikovna|zasilkovna|all)',
)]
class ImportBranchesCommand extends Command
{
    public function __construct(private ManageBranch $manageBranch)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'provider',
            InputArgument::REQUIRED,
            'Provider key: posta | balikovna | zasilkovna | all',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arg = strtolower((string)$input->getArgument('provider'));
        $targets = match ($arg) {
            'posta', 'balikovna', 'zasilkovna' => [$arg],
            'all'                              => ['posta', 'balikovna', 'zasilkovna'],
            default                            => null,
        };

        if ($targets === null) {
            $output->writeln("<error>Unknown provider '{$arg}'. Use: posta | balikovna | zasilkovna | all</error>");
            return Command::FAILURE;
        }

        $anyFailed = false;

        foreach ($targets as $p) {
            try {
                $stats = match ($p) {
                    'posta'      => $this->manageBranch->importNapostu(),
                    'balikovna'  => $this->manageBranch->importBalikovna(),
                    'zasilkovna' => $this->manageBranch->importZasilkovna(),
                };

                $output->writeln(sprintf(
                    'provider %s: processed %d, created %d, updated %d',
                    strtoupper($stats['provider']),
                    $stats['processed'],
                    $stats['created'],
                    $stats['updated'],
                ));

            } catch (Throwable $e) {
                $anyFailed = true;
                $output->writeln(sprintf('<error>%s: import failed — %s</error>', strtoupper($p), $e->getMessage()));
            }
        }

        return $anyFailed ? Command::FAILURE : Command::SUCCESS;
    }
}
