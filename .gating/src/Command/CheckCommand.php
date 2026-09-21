<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Command;

use Gating\Gate\Runner\GatingRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides the check command implementation used by the Gating runtime and rule execution flow.
 */
final class CheckCommand extends AbstractGatingCommand
{
    /**
     * Initializes the collaborators and immutable state required by this Gating component.
     */
    public function __construct(private readonly GatingRunner $runner)
    {
        parent::__construct('check');
        $this->setAliases(['scan', 'report', 'inventory']);
    }

    /**
     * Executes the configure responsibility defined by this Gating component.
     */
    protected function configure(): void
    {
        $this->setDescription('Run read-only platform rules.');
        $this->addCheckOptions();
    }

    /**
     * Executes the execute responsibility defined by this Gating component.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->runCheck($input, $output, $this->resolveInvokedName($input, 'check'));
    }
}
