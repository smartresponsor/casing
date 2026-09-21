<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon009 component host boundary rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon009ComponentHostBoundaryRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.009.component_host_boundary';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hostNamespaces = $this->profileList($context, 'host_namespaces');
        if ([] === $hostNamespaces) {
            return $this->result('skipped', 'No host_namespaces declared in profile.');
        }
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            foreach ($hostNamespaces as $namespace) {
                if (str_contains($contents, $namespace.'\\')) {
                    $hits[] = $this->relative($context, $file->getPathname()).' references Host namespace '.$namespace;
                }
            }
        }

        return [] === $hits ? $this->result('passed', 'Standalone component has no Host implementation dependencies.') : $this->result('failed', 'Host implementation dependencies found in standalone component.', array_values(array_unique($hits)));
    }
}
