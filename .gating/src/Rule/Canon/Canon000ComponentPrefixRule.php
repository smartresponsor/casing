<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon000 component prefix rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon000ComponentPrefixRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.000.component_prefix';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $prefix = $this->profileString($context, 'subject_prefix');
        if (null === $prefix) {
            return $this->result('skipped', 'No subject_prefix is declared in profile.');
        }
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $relative = $this->relative($context, $file->getPathname());
            if ('src/Kernel.php' === $relative || (str_starts_with($relative, 'src/') && !str_contains(substr($relative, 4), '/') && str_ends_with($relative, 'Bundle.php'))) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (false === $contents) {
                continue;
            }
            $declaration = $this->declaration($contents);
            if (null === $declaration) {
                continue;
            }
            if (!str_starts_with($declaration, $prefix)) {
                $hits[] = $this->relative($context, $file->getPathname()).' => '.$declaration;
            }
        }

        return [] === $hits ? $this->result('passed', 'Component subject prefix is canonical.') : $this->result('failed', 'Classes/interfaces must use subject prefix '.$prefix.'.', $hits);
    }
}
