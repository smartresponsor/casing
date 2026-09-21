<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Command;

use Gating\Gate\Runner\GatingRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides the exit codes command implementation used by the Gating runtime and rule execution flow.
 */
final class ExitCodesCommand extends AbstractGatingCommand
{
    /**
     * Initializes the collaborators and immutable state required by this Gating component.
     */
    public function __construct(private readonly GatingRunner $runner)
    {
        parent::__construct('exit-codes');
    }

    /**
     * Executes the configure responsibility defined by this Gating component.
     */
    protected function configure(): void
    {
        $this->setDescription('Show stable CI/command exit codes.');
    }

    /**
     * Executes the execute responsibility defined by this Gating component.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->runExitCodes($input, $output);
    }
}
