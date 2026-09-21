<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon016 explicit compatibility lifecycle rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon016ExplicitCompatibilityLifecycleRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.016.explicit_compatibility_lifecycle';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $name = $file->getBasename('.php');
            if (1 !== preg_match('/(?:Legacy|Compatibility|Compat|Alias)/', $name)) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (1 !== preg_match('/@deprecated|compatibility[-_ ]lifecycle|removal[-_ ]condition/i', $contents)) {
                $hits[] = $this->relative($context, $file->getPathname()).' looks like a compatibility surface without lifecycle metadata.';
            }
        }

        return [] === $hits
            ? $this->result('passed', 'Discovered compatibility surfaces declare lifecycle intent.')
            : $this->result('warning', 'Compatibility surfaces without explicit lifecycle metadata require review.', $hits, 'warning');
    }
}
