<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Command;

use Gating\Gate\Runner\GatingRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides the discover targets command implementation used by the Gating runtime and rule execution flow.
 */
final class DiscoverTargetsCommand extends AbstractGatingCommand
{
    /**
     * Initializes the collaborators and immutable state required by this Gating component.
     */
    public function __construct(private readonly GatingRunner $runner)
    {
        parent::__construct('discover-targets');
        $this->setAliases(['targets', 'target:list']);
    }

    /**
     * Executes the configure responsibility defined by this Gating component.
     */
    protected function configure(): void
    {
        $this->setDescription('Discover candidate component roots under a workspace.');
        $this->addDiscoveryOptions();
    }

    /**
     * Executes the execute responsibility defined by this Gating component.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->runDiscoverTargets($input, $output);
    }
}
