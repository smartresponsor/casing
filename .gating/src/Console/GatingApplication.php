<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Console;

use Gating\Gate\Command\BaselineCommand;
use Gating\Gate\Command\CheckCommand;
use Gating\Gate\Command\DiscoverTargetsCommand;
use Gating\Gate\Command\ExitCodesCommand;
use Gating\Gate\Command\ExportPolicyCommand;
use Gating\Gate\Command\ListRulesCommand;
use Gating\Gate\Command\SelfCheckCommand;
use Gating\Gate\Runner\GatingRunner;
use Gating\Gate\Service\PolicyExportService;
use Symfony\Component\Console\Application;

/**
 * Provides the gating application implementation used by the Gating runtime and rule execution flow.
 */
final class GatingApplication extends Application
{
    /**
     * Initializes the collaborators and immutable state required by this Gating component.
     */
    public function __construct(string $repoRoot)
    {
        parent::__construct('Gating Gate', '1.0.0');

        $runner = new GatingRunner($repoRoot);
        $exporter = new PolicyExportService();
        $this->addCommands([
            new CheckCommand($runner),
            new DiscoverTargetsCommand($runner),
            new BaselineCommand($runner),
            new ExportPolicyCommand($exporter, $runner),
            new ListRulesCommand($runner),
            new SelfCheckCommand($runner),
            new ExitCodesCommand($runner),
        ]);
    }
}
