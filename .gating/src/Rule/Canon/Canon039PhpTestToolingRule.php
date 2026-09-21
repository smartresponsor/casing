<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Enforces the Canon039 PHPUnit tooling and stable coverage-summary execution contract.
 */
final class Canon039PhpTestToolingRule extends AbstractCanonRule
{
    public function id(): string
    {
        return 'canon.039.php_test_tooling';
    }

    public function check(RuleContext $context): RuleResult
    {
        if ([] === $this->phpFiles($context)) {
            return $this->result('skipped', 'No executable production PHP source was discovered under src/.');
        }

        $composerPath = $context->targetPath.'/composer.json';
        if (!is_file($composerPath)) {
            return $this->result('failed', 'Executable PHP source requires composer.json with the canonical PHPUnit tooling contract.');
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);
        if (!is_array($composer)) {
            return $this->result('failed', 'composer.json is invalid.');
        }

        $hits = [];
        $requireDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];
        if (!array_key_exists('phpunit/phpunit', $requireDev)) {
            $hits[] = 'composer.json:require-dev is missing phpunit/phpunit.';
        }

        $configPath = null;
        foreach (['phpunit.xml', 'phpunit.xml.dist'] as $candidate) {
            if (is_file($context->targetPath.'/'.$candidate)) {
                $configPath = $context->targetPath.'/'.$candidate;
                break;
            }
        }

        $config = '';
        if (null === $configPath) {
            $hits[] = 'Missing repository-owned phpunit.xml or phpunit.xml.dist.';
        } else {
            $config = (string) file_get_contents($configPath);
            if (!$this->hasSourceDirectory($config, 'src')) {
                $hits[] = 'PHPUnit configuration does not explicitly include src/ in the source coverage population.';
            }
            if (1 === preg_match('/includeUncoveredFiles\s*=\s*["\']false["\']/i', $config)) {
                $hits[] = 'PHPUnit coverage explicitly excludes completely uncovered source files.';
            }
        }

        $scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
        $commands = $this->scriptCommands($scripts);
        $ordinaryPhpUnit = false;
        $persistentTextCoverage = 1 === preg_match('/<text\\b[^>]*\\boutputFile\\s*=/i', $config);
        $branchCoverage = 1 === preg_match('/\b(?:branchCoverage|pathCoverage)\s*=\s*["\']true["\']/i', $config);

        foreach ($commands as $command) {
            $normalized = strtolower($command);
            if (str_contains($normalized, 'phpunit') && !str_contains($normalized, 'coverage')) {
                $ordinaryPhpUnit = true;
            }
            if (str_contains($normalized, 'phpunit') && 1 === preg_match('/--coverage-text(?:=|\\s+)[^\\s]+/i', $command)) {
                $persistentTextCoverage = true;
            }
            if (str_contains($normalized, '--branch-coverage') || str_contains($normalized, '--path-coverage')) {
                $branchCoverage = true;
            }
        }

        if (!$ordinaryPhpUnit) {
            $hits[] = 'Composer scripts do not expose ordinary PHPUnit execution.';
        }
        if (!$persistentTextCoverage) {
            $hits[] = 'Composer/PHPUnit configuration does not expose a persistent PHPUnit text coverage summary.';
        }
        if (!$branchCoverage) {
            $hits[] = 'Branch coverage is not enabled by PHPUnit configuration or coverage execution options.';
        }

        return [] === $hits
            ? $this->result('passed', 'PHPUnit tooling, source filtering, branch instrumentation, and persistent text coverage summary are configured.')
            : $this->result('failed', 'Canonical PHPUnit testing tooling contract is incomplete.', $hits);
    }

    private function hasSourceDirectory(string $config, string $directory): bool
    {
        if (1 !== preg_match('/<source\b.*?<\/source>/is', $config, $source)) {
            return false;
        }

        if (1 !== preg_match('/<include\b.*?<\/include>/is', $source[0], $include)) {
            return false;
        }

        return 1 === preg_match(
            '#<directory\b[^>]*>\s*(?:\./)?'.preg_quote($directory, '#').'/?\s*</directory>#i',
            $include[0],
        );
    }

    /**
     * @param array<string, mixed> $scripts
     *
     * @return list<string>
     */
    private function scriptCommands(array $scripts): array
    {
        $commands = [];
        $walk = static function (mixed $value) use (&$commands, &$walk): void {
            if (is_string($value)) {
                $commands[] = $value;

                return;
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }
            }
        };

        foreach ($scripts as $script) {
            $walk($script);
        }

        return $commands;
    }
}
