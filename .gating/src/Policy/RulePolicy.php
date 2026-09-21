<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Policy;

use Gating\Gate\Contract\RuleResult;

/**
 * Provides the rule policy implementation used by the Gating runtime and rule execution flow.
 */
final readonly class RulePolicy
{
    /**
     * @param array<string, string>       $severityByRule
     * @param array<string, list<string>> $suppressionsByRule
     */
    public function __construct(
        private string $defaultSeverity = 'error',
        private array $severityByRule = [],
        private array $suppressionsByRule = [],
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $profile
     */
    public static function fromConfigAndProfile(array $config, array $profile): self
    {
        $severity = $config['severity'] ?? [];
        $defaultSeverity = self::normalizeSeverity(is_array($severity) ? (string) ($severity['default'] ?? 'error') : 'error');
        $rules = is_array($severity) && isset($severity['rules']) && is_array($severity['rules']) ? $severity['rules'] : [];

        $severityByRule = [];
        foreach ($rules as $ruleId => $ruleSeverity) {
            if (!is_string($ruleId)) {
                continue;
            }
            $severityByRule[$ruleId] = self::normalizeSeverity((string) $ruleSeverity);
        }

        return new self($defaultSeverity, $severityByRule, self::extractSuppressions($profile));
    }

    /**
     * @param list<RuleResult> $results
     *
     * @return list<RuleResult>
     */
    public function apply(array $results): array
    {
        $effective = [];
        foreach ($results as $result) {
            $severity = $this->severityFor($result->rule);
            if ('failed' !== $result->status) {
                $effective[] = new RuleResult($result->rule, $result->status, $result->message, $result->evidence, $severity);
                continue;
            }

            if ($this->isSuppressed($result)) {
                $effective[] = new RuleResult(
                    $result->rule,
                    'suppressed',
                    $result->message.' Suppressed by component profile exception.',
                    array_merge(['Suppression matched profile exception for rule '.$result->rule.'.'], $result->evidence),
                    $severity,
                );
                continue;
            }

            if ('warning' === $severity) {
                $effective[] = new RuleResult($result->rule, 'warning', $result->message, $result->evidence, $severity);
                continue;
            }

            if ('info' === $severity) {
                $effective[] = new RuleResult($result->rule, 'info', $result->message, $result->evidence, $severity);
                continue;
            }

            $effective[] = new RuleResult($result->rule, 'failed', $result->message, $result->evidence, $severity);
        }

        return $effective;
    }

    /**
     * Executes the severity for responsibility defined by this Gating component.
     */
    private function severityFor(string $ruleId): string
    {
        return $this->severityByRule[$ruleId] ?? $this->defaultSeverity;
    }

    /**
     * Normalizes severity into the canonical representation used by Gating.
     */
    private static function normalizeSeverity(string $severity): string
    {
        return in_array($severity, ['error', 'warning', 'info'], true) ? $severity : 'error';
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return array<string, list<string>>
     */
    private static function extractSuppressions(array $profile): array
    {
        $raw = $profile['suppressions'] ?? $profile['exceptions'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $suppressions = [];
        foreach ($raw as $ruleId => $patterns) {
            if (!is_string($ruleId)) {
                continue;
            }
            if (is_string($patterns)) {
                $suppressions[$ruleId] = [$patterns];
                continue;
            }
            if (!is_array($patterns)) {
                continue;
            }
            $values = [];
            foreach ($patterns as $pattern) {
                if (is_string($pattern) && '' !== $pattern) {
                    $values[] = $pattern;
                }
            }
            if ([] !== $values) {
                $suppressions[$ruleId] = $values;
            }
        }

        return $suppressions;
    }

    /**
     * Determines whether suppressed satisfies the current Gating condition.
     */
    private function isSuppressed(RuleResult $result): bool
    {
        $patterns = $this->suppressionsByRule[$result->rule] ?? [];
        if ([] === $patterns) {
            return false;
        }
        if ([] === $result->evidence) {
            return in_array('*', $patterns, true);
        }

        foreach ($result->evidence as $evidence) {
            $matched = false;
            foreach ($patterns as $pattern) {
                if ('*' === $pattern || str_contains($evidence, $pattern)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }
}
