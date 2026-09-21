<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Reporter;

use Gating\Gate\Contract\RuleResult;

/**
 * Provides the console reporter implementation used by the Gating runtime and rule execution flow.
 */
final readonly class ConsoleReporter
{
    /** @param list<RuleResult> $results */
    public function printText(array $results): int
    {
        $summary = $this->summary($results);
        foreach ($results as $result) {
            $status = strtoupper($result->status);
            echo sprintf('[%s/%s] %s - %s', $status, strtoupper($result->severity), $result->rule, $result->message).PHP_EOL;
            foreach (array_slice($result->evidence, 0, 20) as $evidence) {
                echo '  - '.$evidence.PHP_EOL;
            }
            if (count($result->evidence) > 20) {
                echo sprintf('  ... %d more', count($result->evidence) - 20).PHP_EOL;
            }
        }

        echo sprintf(
            'Gating result: %d rule(s), %d failed, %d warning, %d suppressed, %d skipped.',
            $summary['total'],
            $summary['failed'],
            $summary['warning'],
            $summary['suppressed'],
            $summary['skipped']
        ).PHP_EOL;

        return $summary['failed'] > 0 ? 1 : 0;
    }

    /**
     * @param list<RuleResult>     $results
     * @param array<string, mixed> $profile
     */
    public function toJson(array $results, string $targetPath, array $profile): string
    {
        $summary = $this->summary($results);
        $payload = [
            'schema' => 'gating.report.v1',
            'tool' => 'gating/gate',
            'generated_at' => gmdate('c'),
            'target' => $targetPath,
            'status' => $summary['failed'] > 0 ? 'failed' : ($summary['warning'] > 0 ? 'warning' : 'passed'),
            'summary' => $summary,
            'profile' => $profile,
            'results' => array_map(static fn (RuleResult $result): array => [
                'rule' => $result->rule,
                'status' => $result->status,
                'severity' => $result->severity,
                'message' => $result->message,
                'evidence' => $result->evidence,
            ], $results),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    /**
     * @param list<RuleResult> $results
     *
     * @return array{total:int,passed:int,failed:int,skipped:int,warning:int,suppressed:int,info:int}
     */
    private function summary(array $results): array
    {
        $summary = [
            'total' => count($results),
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'warning' => 0,
            'suppressed' => 0,
            'info' => 0,
        ];

        foreach ($results as $result) {
            if (array_key_exists($result->status, $summary)) {
                ++$summary[$result->status];
            }
        }

        return $summary;
    }
}
