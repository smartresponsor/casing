<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Enforces the Canon041 Symfony functional/browser/UI testing tooling contract.
 */
final class Canon041BehavioralUiTestToolingRule extends AbstractCanonRule
{
    public function id(): string
    {
        return 'canon.041.behavioral_ui_test_tooling';
    }

    public function check(RuleContext $context): RuleResult
    {
        if (!$this->isSymfonyApplication($context)) {
            return $this->result('skipped', 'No standalone Symfony application runtime was detected.');
        }

        $hits = [];
        $composerPath = $context->targetPath.'/composer.json';
        $composer = is_file($composerPath) ? json_decode((string) file_get_contents($composerPath), true) : null;
        if (!is_array($composer)) {
            return $this->result('failed', 'Standalone Symfony applications require composer.json with the canonical behavioral/UI testing contract.');
        }

        $requireDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];
        foreach (['symfony/test-pack', 'symfony/panther'] as $package) {
            if (!array_key_exists($package, $requireDev)) {
                $hits[] = 'composer.json:require-dev is missing '.$package.'.';
            }
        }

        $composerScripts = $this->flattenScripts(is_array($composer['scripts'] ?? null) ? $composer['scripts'] : []);
        if (!$this->containsCommand($composerScripts, 'phpunit')) {
            $hits[] = 'Composer scripts do not expose PHPUnit/Symfony application test execution.';
        }

        $packagePath = $context->targetPath.'/package.json';
        $package = is_file($packagePath) ? json_decode((string) file_get_contents($packagePath), true) : null;
        if (!is_array($package)) {
            $hits[] = 'Missing valid package.json with repository-local Playwright tooling.';
        } else {
            $devDependencies = is_array($package['devDependencies'] ?? null) ? $package['devDependencies'] : [];
            if (!array_key_exists('@playwright/test', $devDependencies)) {
                $hits[] = 'package.json:devDependencies is missing @playwright/test.';
            }

            $npmScripts = $this->flattenScripts(is_array($package['scripts'] ?? null) ? $package['scripts'] : []);
            if (!$this->containsCommand($npmScripts, 'playwright test')) {
                $hits[] = 'package.json scripts do not expose Playwright test execution.';
            }
        }

        $playwrightConfig = false;
        foreach (['playwright.config.ts', 'playwright.config.js', 'playwright.config.mts', 'playwright.config.mjs', 'playwright.config.cts', 'playwright.config.cjs'] as $candidate) {
            if (is_file($context->targetPath.'/'.$candidate)) {
                $playwrightConfig = true;
                break;
            }
        }
        if (!$playwrightConfig) {
            $hits[] = 'Missing repository-owned Playwright configuration.';
        }

        return [] === $hits
            ? $this->result('passed', 'Symfony Test Pack, Panther, and Playwright tooling are configured.')
            : $this->result('failed', 'Canonical behavioral/UI testing tooling contract is incomplete.', $hits);
    }

    private function isSymfonyApplication(RuleContext $context): bool
    {
        $composerPath = $context->targetPath.'/composer.json';
        if (is_file($composerPath)) {
            $composer = json_decode((string) file_get_contents($composerPath), true);
            if (is_array($composer)) {
                $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
                if (array_key_exists('symfony/framework-bundle', $require)) {
                    return true;
                }
            }
        }

        return is_file($context->targetPath.'/bin/console')
            && is_file($context->targetPath.'/config/bundles.php')
            && (is_file($context->targetPath.'/app/Kernel.php') || is_file($context->targetPath.'/src/Kernel.php'));
    }

    /** @param array<string, mixed> $scripts
     * @return list<string>
     */
    private function flattenScripts(array $scripts): array
    {
        $commands = [];
        $walk = static function (mixed $value) use (&$commands, &$walk): void {
            if (is_string($value)) {
                $commands[] = strtolower($value);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }
            }
        };
        foreach ($scripts as $value) {
            $walk($value);
        }

        return $commands;
    }

    /** @param list<string> $commands */
    private function containsCommand(array $commands, string $needle): bool
    {
        foreach ($commands as $command) {
            if (str_contains($command, $needle)) {
                return true;
            }
        }

        return false;
    }
}
