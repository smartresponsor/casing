<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Contract;

/**
 * Provides the rule result implementation used by the Gating runtime and rule execution flow.
 */
final readonly class RuleResult
{
    /** @param list<string> $evidence */
    public function __construct(
        public string $rule,
        public string $status,
        public string $message,
        public array $evidence = [],
        public string $severity = 'error',
    ) {
    }
}
