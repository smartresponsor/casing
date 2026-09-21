<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Provides the abstract gating command implementation used by the Gating runtime and rule execution flow.
 */
abstract class AbstractGatingCommand extends Command
{
    /**
     * Resolves invoked name from the available Gating inputs and defaults.
     */
    protected function resolveInvokedName(InputInterface $input, string $default): string
    {
        if ($input instanceof ArgvInput) {
            $firstArgument = $input->getFirstArgument();
            if (is_string($firstArgument) && '' !== $firstArgument && !str_starts_with($firstArgument, '-')) {
                return $firstArgument;
            }
        }

        return $default;
    }

    /**
     * Adds check options to the current Gating command or configuration surface.
     */
    protected function addCheckOptions(): void
    {
        $this
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Target path to inspect.', '.')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Profile path to load.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format.', 'text')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON report.')
            ->addOption('report-file', null, InputOption::VALUE_REQUIRED, 'Write the JSON report to this file.')
            ->addOption('rule-set', null, InputOption::VALUE_REQUIRED, 'Rule-set profile path.')
            ->addOption('severity-config', null, InputOption::VALUE_REQUIRED, 'Severity configuration file path.')
            ->addOption('policy-root', null, InputOption::VALUE_REQUIRED, 'Explicit .gating policy root.')
            ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Workspace root for discovery and fallback resolution.', '.')
            ->addOption('max-depth', null, InputOption::VALUE_REQUIRED, 'Maximum discovery depth.', 2);
    }

    /**
     * Adds discovery options to the current Gating command or configuration surface.
     */
    protected function addDiscoveryOptions(): void
    {
        $this
            ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Workspace root for discovery.', '.')
            ->addOption('max-depth', null, InputOption::VALUE_REQUIRED, 'Maximum discovery depth.', 2)
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format.', 'text')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON report.');
    }

    /**
     * Adds baseline options to the current Gating command or configuration surface.
     */
    protected function addBaselineOptions(): void
    {
        $this
            ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Workspace root for discovery.', '.')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Profile path to load.')
            ->addOption('report-file', null, InputOption::VALUE_REQUIRED, 'Write the JSON report to this file.')
            ->addOption('rule-set', null, InputOption::VALUE_REQUIRED, 'Rule-set profile path.')
            ->addOption('severity-config', null, InputOption::VALUE_REQUIRED, 'Severity configuration file path.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format.', 'text')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON report.')
            ->addOption('max-depth', null, InputOption::VALUE_REQUIRED, 'Maximum discovery depth.', 2);
    }

    /**
     * Adds list rules options to the current Gating command or configuration surface.
     */
    protected function addListRulesOptions(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format.', 'text')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON report.');
    }

    /**
     * Adds self check options to the current Gating command or configuration surface.
     */
    protected function addSelfCheckOptions(): void
    {
        $this->addCheckOptions();
    }
}
