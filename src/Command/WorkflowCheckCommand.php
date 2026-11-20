<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Command;

use LogicException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Greendot\EshopBundle\Workflow\Action\ActionEntry;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionActionResolver;

#[AsCommand(
    name: 'greendot:eshop:workflow:check',
    description: 'List the actions of every purchase_flow transition and fail on unknown or malformed ones',
)]
class WorkflowCheckCommand extends Command
{
    public function __construct(
        #[Target('purchase_flow')]
        private readonly WorkflowInterface                $purchaseWorkflow,
        private readonly PurchaseTransitionActionResolver $resolver,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        $errors = [];

        foreach ($this->purchaseWorkflow->getDefinition()->getTransitions() as $transition) {
            try {
                $actions = implode(', ', array_map(
                    static fn (ActionEntry $e) => $e->key . ($e->skippable ? ' (skippable)' : ''),
                    $this->resolver->entries($this->purchaseWorkflow, $transition),
                ));
            } catch (LogicException $e) {
                $errors[] = $e->getMessage();
                $actions = '<error>invalid</error>';
            }

            $rows[] = [
                $transition->getName(),
                implode(', ', $transition->getFroms()) . ' → ' . implode(', ', $transition->getTos()),
                $actions,
            ];
        }

        $io->table(['Transition', 'From → to', 'Actions'], $rows);

        if ($errors) {
            $io->error(array_values(array_unique($errors)));

            return Command::FAILURE;
        }

        $io->success('All transition actions resolve.');

        return Command::SUCCESS;
    }
}
