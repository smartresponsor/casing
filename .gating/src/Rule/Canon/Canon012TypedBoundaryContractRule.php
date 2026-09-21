<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon012 typed boundary contract rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon012TypedBoundaryContractRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.012.typed_boundary_contract';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $roots = $this->profileList($context, 'typed_boundary_internal_roots');
        if ([] === $roots) {
            return $this->result('skipped', 'No typed_boundary_internal_roots are declared in profile.');
        }

        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $relative = $this->relative($context, $file->getPathname());
            $root = explode('/', $relative)[1] ?? '';
            if (!in_array($root, $roots, true)) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (1 === preg_match('/public\s+function\s+\w+\s*\([^)]*\)\s*:\s*(?:array|mixed)\b/s', $contents)) {
                $hits[] = $relative.' exposes array/mixed as a stable internal return contract.';
            }
        }

        return [] === $hits
            ? $this->result('passed', 'Configured internal role roots expose typed return contracts.')
            : $this->result('warning', 'Dynamic internal contracts require typed-boundary review.', $hits, 'warning');
    }
}
