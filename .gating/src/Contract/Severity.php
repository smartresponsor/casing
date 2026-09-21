<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Contract;

/**
 * Provides the severity implementation used by the Gating runtime and rule execution flow.
 */
enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
