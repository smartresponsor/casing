<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon013 no placeholder production logic rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon013NoPlaceholderProductionLogicRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.013.no_placeholder_production_logic';
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
            if (1 === preg_match('/throw\s+new\s+\\\\?(?:LogicException|RuntimeException)\s*\(\s*[\'\"](?:not\s+implemented|todo|placeholder)/i', $contents)) {
                $hard[] = $relative.' contains an explicit not-implemented production exception.';
            }
            if (1 === preg_match('#(?://|/\*|\*)\s*(?:TODO|FIXME)\b#i', $contents)) {
                $semantic[] = $relative.' contains TODO/FIXME in src/.';
            }
        }

        if ([] !== $hard) {
            return $this->result('failed', 'Production implementation contains placeholder logic.', $hard);
        }
        if ([] !== $semantic) {
            return $this->result('warning', 'Production TODO/FIXME markers require readiness review.', $semantic, 'warning');
        }

        return $this->result('passed', 'No known production placeholder patterns were detected.');
    }
}
