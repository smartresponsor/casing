<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Kernel;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the gate kernel implementation used by the Gating runtime and rule execution flow.
 */
final readonly class GateKernel
{
    /** @param list<RuleInterface> $rules */
    public function __construct(private array $rules)
    {
    }

    /** @return list<RuleResult> */
    public function check(RuleContext $context): array
    {
        $results = [];
        foreach ($this->rules as $rule) {
            $results[] = $rule->check($context);
        }

        return $results;
    }
}
