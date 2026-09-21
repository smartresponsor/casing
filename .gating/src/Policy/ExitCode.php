<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Policy;

/**
 * Provides the exit code implementation used by the Gating runtime and rule execution flow.
 */
final readonly class ExitCode
{
    public const int PASSED = 0;
    public const int POLICY_FAILED = 1;
    public const int USAGE_ERROR = 2;
    public const int INTERNAL_ERROR = 3;
}
