<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Evaluates Canon040 executable coverage thresholds from the stable PHPUnit text summary.
 */
final class Canon040PhpTestCoverageRule extends AbstractCanonRule
{
    private const float LINE_THRESHOLD = 80.0;
    private const float METHOD_THRESHOLD = 80.0;
    private const float BRANCH_THRESHOLD = 70.0;
    private const float HIGH_DEBT_LINE = 50.0;
    private const float HIGH_DEBT_METHOD = 50.0;
    private const float HIGH_DEBT_BRANCH = 40.0;

    public function id(): string
    {
        return 'canon.040.php_test_coverage';
    }

    public function check(RuleContext $context): RuleResult
    {
        $phpFiles = $this->phpFiles($context);
        if ([] === $phpFiles) {
            return $this->result('skipped', 'No executable production PHP source was discovered under src/.');
        }

        $coverageSummaryPath = $this->coverageSummaryPath($context);
        if (null === $coverageSummaryPath) {
            return $this->result(
                'warning',
                'No discoverable persistent PHPUnit text coverage summary is configured.',
                ['Generate canonical text coverage before evaluating Canon040.'],
                'warning',
            );
        }

        if (!is_file($coverageSummaryPath)) {
            return $this->result(
                'warning',
                'Canonical PHPUnit text coverage evidence is missing.',
                [$this->relativeCoveragePath($context, $coverageSummaryPath)],
                'warning',
            );
        }

        $latestSourceTimestamp = max(array_map(
            static fn (\SplFileInfo $file): int => $file->getMTime(),
            $phpFiles,
        ));
        $coverageTimestamp = filemtime($coverageSummaryPath);
        if (false !== $coverageTimestamp && $coverageTimestamp < $latestSourceTimestamp) {
            return $this->result(
                'warning',
                'Canonical PHPUnit coverage evidence is stale relative to production source.',
                [$this->relativeCoveragePath($context, $coverageSummaryPath)],
                'warning',
            );
        }

        $coverageText = (string) file_get_contents($coverageSummaryPath);
        $lines = $this->metric($coverageText, 'Lines');
        $methods = $this->metric($coverageText, 'Methods');
        $branches = $this->metric($coverageText, 'Branches');
        if (null === $lines || null === $methods || null === $branches) {
            $missing = [];
            foreach (['Lines' => $lines, 'Methods' => $methods, 'Branches' => $branches] as $name => $metric) {
                if (null === $metric) {
                    $missing[] = 'Missing '.$name.' metric in PHPUnit coverage summary.';
                }
            }

            return $this->result(
                'warning',
                'Canonical PHPUnit text coverage evidence is incomplete or invalid.',
                $missing,
                'warning',
            );
        }

        [$executedLines, $executableLines] = $lines;
        [$coveredMethods, $methodTotal] = $methods;
        [$executedBranches, $branchTotal] = $branches;
        $lineCoverage = 0 === $executableLines ? 100.0 : 100.0 * $executedLines / $executableLines;
        $methodCoverage = 0 === $methodTotal ? 100.0 : 100.0 * $coveredMethods / $methodTotal;
        $branchCoverage = 0 === $branchTotal ? 100.0 : 100.0 * $executedBranches / $branchTotal;
        $evidence = [
            sprintf('Lines %d/%d; methods %d/%d; branches %d/%d.', $executedLines, $executableLines, $coveredMethods, $methodTotal, $executedBranches, $branchTotal),
        ];

        $highDebt = $lineCoverage < self::HIGH_DEBT_LINE
            || $methodCoverage < self::HIGH_DEBT_METHOD
            || $branchCoverage < self::HIGH_DEBT_BRANCH;

        $summary = sprintf(
            'PHP test coverage: lines %.1f%% (target %.0f%%), methods %.1f%% (target %.0f%%), branches %.1f%% (target %.0f%%)%s.',
            $lineCoverage,
            self::LINE_THRESHOLD,
            $methodCoverage,
            self::METHOD_THRESHOLD,
            $branchCoverage,
            self::BRANCH_THRESHOLD,
            $highDebt ? '; HIGH_TEST_DEBT' : '',
        );

        if ($lineCoverage >= self::LINE_THRESHOLD
            && $methodCoverage >= self::METHOD_THRESHOLD
            && $branchCoverage >= self::BRANCH_THRESHOLD) {
            return $this->result('passed', $summary);
        }

        if ($highDebt) {
            array_unshift($evidence, 'HIGH_TEST_DEBT: repository is eligible for automated test-remediation queue admission.');
        }

        return $this->result('warning', $summary, $evidence, 'warning');
    }

    /** @return array{int, int}|null */
    private function metric(string $coverageText, string $name): ?array
    {
        $coverageText = preg_replace('/\\x1B\\[[0-?]*[ -\\/]*[@-~]/', '', $coverageText) ?? $coverageText;
        if (1 !== preg_match('/^\\s*'.preg_quote($name, '/').':\\s+\\S+\\s+\\(\\s*(\\d+)\\s*\\/\\s*(\\d+)\\s*\\)\\s*$/mi', $coverageText, $match)) {
            return null;
        }

        $executed = (int) $match[1];
        $total = (int) $match[2];
        if ($executed < 0 || $total < 0 || $executed > $total) {
            return null;
        }

        return [$executed, $total];
    }

    private function coverageSummaryPath(RuleContext $context): ?string
    {
        $config = '';
        foreach (['phpunit.xml', 'phpunit.xml.dist'] as $candidate) {
            $path = $context->targetPath.'/'.$candidate;
            if (is_file($path)) {
                $config = (string) file_get_contents($path);
                if (1 === preg_match('/<text\b[^>]*\boutputFile\s*=\s*["\']([^"\']+)["\']/i', $config, $match)) {
                    return $this->absoluteCoveragePath($context, $match[1]);
                }
                break;
            }
        }

        $composerPath = $context->targetPath.'/composer.json';
        if (!is_file($composerPath)) {
            return null;
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);
        if (!is_array($composer) || !is_array($composer['scripts'] ?? null)) {
            return null;
        }

        $scriptText = json_encode($composer['scripts'], JSON_UNESCAPED_SLASHES) ?: '';
        if (1 === preg_match('/--coverage-text(?:=|\s+)([^\s"\']+)/i', $scriptText, $match)) {
            return $this->absoluteCoveragePath($context, $match[1]);
        }

        return null;
    }

    private function absoluteCoveragePath(RuleContext $context, string $path): string
    {
        $path = trim($path);
        if (1 === preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path)) {
            return rtrim(str_replace('\\', '/', $path), '/');
        }

        return rtrim(str_replace('\\', '/', $context->targetPath.'/'.$path), '/');
    }

    private function relativeCoveragePath(RuleContext $context, string $path): string
    {
        $normalizedTarget = rtrim(str_replace('\\', '/', $context->targetPath), '/').'/';
        $normalizedPath = str_replace('\\', '/', $path);

        return str_starts_with($normalizedPath, $normalizedTarget)
            ? substr($normalizedPath, strlen($normalizedTarget))
            : $normalizedPath;
    }
}
