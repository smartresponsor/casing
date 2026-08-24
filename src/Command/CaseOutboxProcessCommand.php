<?php

declare(strict_types=1);

namespace App\Casing\Command;

use App\Casing\Service\Outbox\CaseOutboxProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'case:outbox:process', description: 'Dispatch pending Casing lifecycle outbox events.')]
final class CaseOutboxProcessCommand extends Command
{
    public function __construct(private readonly CaseOutboxProcessor $processor)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->processor->process();
        $output->writeln(sprintf('Dispatched %d Casing outbox event(s).', $count));

        return Command::SUCCESS;
    }
}
