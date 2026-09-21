<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon011 no silent failure rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon011NoSilentFailureRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.011.no_silent_failure';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hard = [];
        $semantic = [];
        foreach ($this->phpFiles($context) as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            $relative = $this->relative($context, $file->getPathname());
            if (1 === preg_match('/catch\s*\([^)]*\)\s*\{\s*\}/s', $contents)) {
                $hard[] = $relative.' contains an empty catch block.';
            }
            if (1 === preg_match('/catch\s*\([^)]*\)\s*\{\s*return\s+(?:null|false|\[\])\s*;\s*\}/s', $contents)) {
                $semantic[] = $relative.' catches an exception and returns an empty success-like value.';
            }
        }

        if ([] !== $hard) {
            return $this->result('failed', 'Mandatory failures must not be silently swallowed.', $hard);
        }
        if ([] !== $semantic) {
            return $this->result('warning', 'Potential silent fallback requires contract review.', $semantic, 'warning');
        }

        return $this->result('passed', 'No known silent-failure patterns were detected.');
    }
}
