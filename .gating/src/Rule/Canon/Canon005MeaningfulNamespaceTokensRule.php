<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon005 meaningful namespace tokens rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon005MeaningfulNamespaceTokensRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.005.meaningful_namespace_tokens';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $patterns = ['/Service/Domain/Application/Data/', '/Event/Domain/', '/Service/Application/Data/'];
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $relative = '/'.$this->relative($context, $file->getPathname());
            foreach ($patterns as $pattern) {
                if (str_contains($relative, $pattern)) {
                    $hits[] = ltrim($relative, '/').' matched '.$pattern;
                }
            }
        }

        return [] === $hits ? $this->result('passed', 'No known ceremonial namespace patterns detected.', [], 'warning') : $this->result('failed', 'Known ceremonial namespace patterns require review.', $hits, 'warning');
    }
}
