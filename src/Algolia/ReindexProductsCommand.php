<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Algolia;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'greendot:eshop:algolia:reindex-products',
    description: 'Re-creates the Algolia product index via the configured ProductIndexerInterface.',
)]
final class ReindexProductsCommand extends Command
{
    public function __construct(
        private readonly ProductIndexerInterface $indexer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'index',
            null,
            InputOption::VALUE_OPTIONAL,
            'Overrides the configured index name for this run',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $indexName = $input->getOption('index');

        $io->title(sprintf('Re-indexing "%s" into Algolia', $indexName ?? '(default)'));
        $io->text(sprintf('%d products to index...', $this->indexer->countProducts()));

        try {
            $result = $this->indexer->reindex($indexName);
        } catch (\Throwable $e) {
            $io->error(sprintf('Algolia reindex failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Indexed %d products into "%s" in %.2fs',
            $result->indexed,
            $result->indexName,
            $result->durationSeconds,
        ));

        return Command::SUCCESS;
    }
}
