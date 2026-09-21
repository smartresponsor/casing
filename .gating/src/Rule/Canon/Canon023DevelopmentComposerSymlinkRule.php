<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon023 development composer symlink rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon023DevelopmentComposerSymlinkRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.023.development_composer_symlink';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $path = $context->targetPath.'/composer.json';
        if (!is_file($path)) {
            return $this->result('skipped', 'Target has no composer.json.');
        }

        $composer = json_decode((string) file_get_contents($path), true);
        if (!is_array($composer)) {
            return $this->result('failed', 'composer.json is invalid.');
        }

        $hits = [];
        foreach (($composer['repositories'] ?? []) as $repository) {
            if (!is_array($repository) || 'path' !== ($repository['type'] ?? null)) {
                continue;
            }

            $url = $repository['url'] ?? null;
            if (!is_string($url) || !str_starts_with(str_replace('\\', '/', $url), '../')) {
                continue;
            }

            if (true !== ($repository['options']['symlink'] ?? null)) {
                $hits[] = 'Local path repository '.$url.' must declare options.symlink=true.';
            }
        }

        return [] === $hits
            ? $this->result('passed', 'Development local path repositories use symlinks.')
            : $this->result('failed', 'Development Composer symlink policy violations found.', $hits);
    }
}
