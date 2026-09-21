<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon014 executable responsibility rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon014ExecutableResponsibilityRule extends AbstractCanonRule
{
    private const array EXECUTABLE_ROOTS = ['Command', 'Runner', 'Handler', 'Invoker'];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.014.executable_responsibility';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $limit = (int) ($this->profileString($context, 'executable_responsibility_max_lines') ?? '650');
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $relative = $this->relative($context, $file->getPathname());
            $root = explode('/', $relative)[1] ?? '';
            if (!in_array($root, self::EXECUTABLE_ROOTS, true)) {
                continue;
            }

            $lines = count(file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: []);
            if ($lines > $limit) {
                $hits[] = sprintf('%s has %d lines; review orchestration versus subordinate responsibilities.', $relative, $lines);
            }
        }

        return [] === $hits
            ? $this->result('passed', 'No executable object exceeded the responsibility review threshold.')
            : $this->result('warning', 'Large executable objects require responsibility review.', $hits, 'warning');
    }
}
