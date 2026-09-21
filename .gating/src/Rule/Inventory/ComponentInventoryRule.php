<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Inventory;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;
use Gating\Gate\Inventory\InventoryScanner;

/**
 * Provides the component inventory rule implementation used by the Gating runtime and rule execution flow.
 */
final readonly class ComponentInventoryRule implements RuleInterface
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'inventory.component_surface';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $declared = $context->profile['component']['inventory_excluded_paths']
            ?? $context->profile['inventory_excluded_paths']
            ?? [];
        $excludedPaths = is_array($declared)
            ? array_values(array_filter($declared, static fn (mixed $path): bool => is_string($path) && '' !== $path))
            : [];

        $counters = new InventoryScanner()->scan($context->targetPath, $excludedPaths);
        $evidence = [];
        foreach ($counters as $nameEntity => $count) {
            $evidence[] = sprintf('%s: %d', $nameEntity, $count);
        }

        return new RuleResult(
            $this->id(),
            'passed',
            'Application-owned component inventory was collected for reporting.',
            $evidence,
        );
    }
}
