<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon004 subject folder placement rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon004SubjectFolderPlacementRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.004.subject_folder_placement';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $tokens = $this->profileList($context, 'subject_tokens');
        $prefix = $this->profileString($context, 'subject_prefix');
        if (null === $prefix && is_file($context->targetPath.'/composer.json')) {
            $composer = json_decode((string) file_get_contents($context->targetPath.'/composer.json'), true);
            $packageName = is_array($composer) ? ($composer['name'] ?? null) : null;
            if (is_string($packageName)) {
                $packageTokens = explode('/', $packageName, 2);
                if (2 === count($packageTokens) && '' !== trim($packageTokens[1])) {
                    $prefix = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $packageTokens[1])));
                }
            }
        }
        if (null !== $prefix) {
            $tokens[] = $prefix;
        }
        $tokens = array_values(array_unique($tokens));
        if ([] === $tokens) {
            return $this->result('skipped', 'No subject identity could be derived from profile or Composer package identity.');
        }
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $parts = explode('/', $this->relative($context, $file->getPathname()));
            foreach ($tokens as $token) {
                $position = array_search($token, $parts, true);
                if (false === $position || $position >= 3) {
                    continue;
                }
                if (2 === $position && 'Entity' === ($parts[1] ?? null)) {
                    continue;
                }
                $hits[] = implode('/', $parts).' uses '.$token.' before level 4.';
            }
        }

        return [] === $hits ? $this->result('passed', 'Subject folder placement is canonical.') : $this->result('failed', 'Premature subject folders found.', array_values(array_unique($hits)));
    }
}
