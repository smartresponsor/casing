<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Command;

use Gating\Gate\Runner\GatingRunner;
use Gating\Gate\Service\PolicyExportService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides the export policy command implementation used by the Gating runtime and rule execution flow.
 */
final class ExportPolicyCommand extends AbstractGatingCommand
{
    /**
     * Initializes the collaborators and immutable state required by this Gating component.
     */
    public function __construct(
        private readonly PolicyExportService $exporter,
        private readonly GatingRunner $runner,
    ) {
        parent::__construct('export-policy');
    }

    /**
     * Executes the configure responsibility defined by this Gating component.
     */
    protected function configure(): void
    {
        $this->setDescription('Export the canonical versioned policy artifact.');
        $this->addOption('policy-root', null, InputOption::VALUE_REQUIRED, 'Explicit .gating policy root.', null);
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Write the artifact JSON to a file instead of stdout.', null);
        $this->addOption('pretty', null, InputOption::VALUE_NONE, 'Pretty-print JSON output.');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON output.');
    }

    /**
     * Executes the execute responsibility defined by this Gating component.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->runExportPolicy($input, $output, $this->exporter);
    }
}
