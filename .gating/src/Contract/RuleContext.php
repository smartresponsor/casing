<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Contract;

/**
 * Provides the rule context implementation used by the Gating runtime and rule execution flow.
 */
final readonly class RuleContext
{
    /** @param array<string, mixed> $profile */
    public function __construct(
        public string $targetPath,
        public array $profile = [],
    ) {
    }
}
