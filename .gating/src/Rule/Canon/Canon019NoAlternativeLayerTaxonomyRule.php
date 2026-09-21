<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon019 no alternative layer taxonomy rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon019NoAlternativeLayerTaxonomyRule extends AbstractCanonRule
{
    private const array FORBIDDEN_ROOTS = ['Domain', 'Application', 'Infrastructure', 'Port', 'Adapter', 'Adaptor'];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.019.no_alternative_layer_taxonomy';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hits = [];
        foreach (self::FORBIDDEN_ROOTS as $root) {
            if (is_dir($context->targetPath.'/src/'.$root)) {
                $hits[] = 'src/'.$root.'/ is a competing architecture layer root.';
            }
        }

        return [] === $hits
            ? $this->result('passed', 'No competing layer taxonomy roots were found.')
            : $this->result('failed', 'Alternative layer taxonomy is not canonical for SmartResponsor components.', $hits);
    }
}
