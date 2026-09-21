<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon017 documentation matches runtime rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon017DocumentationMatchesRuntimeRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.017.documentation_matches_runtime';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $tokens = $this->profileList($context, 'stale_documentation_tokens');
        if ([] === $tokens) {
            return $this->result('skipped', 'No stale_documentation_tokens are declared in profile.');
        }

        $hits = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($context->targetPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !in_array(strtolower($file->getExtension()), ['md', 'adoc', 'rst'], true)) {
                continue;
            }
            if ('CMCP_CHANGELOG.md' === $file->getBasename()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            if (str_contains($path, '/vendor/') || str_contains($path, '/var/') || str_contains($path, '/.git/')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            foreach ($tokens as $token) {
                if (str_contains($contents, $token)) {
                    $hits[] = $this->relative($context, $file->getPathname()).' contains stale documentation token '.$token;
                }
            }
        }

        return [] === $hits
            ? $this->result('passed', 'Configured stale runtime/documentation tokens were not found.')
            : $this->result('failed', 'Documentation contains configured stale runtime/API references.', array_values(array_unique($hits)));
    }
}
