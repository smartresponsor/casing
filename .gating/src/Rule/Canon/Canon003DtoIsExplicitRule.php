<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon003 dto is explicit rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon003DtoIsExplicitRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.003.dto_explicit';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $relative = $this->relative($context, $file->getPathname());
            $name = $file->getBasename('.php');
            $inDto = str_starts_with($relative, 'src/DTO/');
            if (str_starts_with($relative, 'src/Dto/')) {
                $hits[] = $relative.' uses Dto instead of DTO.';
            }
            if ($inDto && !str_ends_with($name, 'DTO')) {
                $hits[] = $relative.' lacks DTO suffix.';
            }
            if (str_ends_with($name, 'DTO') && !$inDto) {
                $hits[] = $relative.' must live under src/DTO/.';
            }
        }

        return [] === $hits ? $this->result('passed', 'DTO placement/casing/suffix are canonical.') : $this->result('failed', 'DTO convention violations found.', $hits);
    }
}
