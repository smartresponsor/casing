<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon010 architecture migration completeness rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon010ArchitectureMigrationCompletenessRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.010.migration_completeness';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $tokens = $this->profileList($context, 'forbidden_legacy_tokens');
        if ([] === $tokens) {
            return $this->result('skipped', 'No forbidden_legacy_tokens declared for migration completeness.');
        }
        $hits = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($context->targetPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            if ('CMCP_CHANGELOG.md' === $file->getBasename()) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if (str_contains($path, '/.git/') || str_contains($path, '/.gating/') || str_contains($path, '/vendor/') || str_contains($path, '/node_modules/') || str_contains($path, '/var/')) {
                continue;
            }
            $contents = @file_get_contents($file->getPathname());
            if (false === $contents) {
                continue;
            }
            foreach ($tokens as $token) {
                if (str_contains($contents, $token)) {
                    $hits[] = $this->relative($context, $file->getPathname()).' contains legacy token '.$token;
                }
            }
        }

        return [] === $hits ? $this->result('passed', 'No configured legacy architecture references remain.') : $this->result('failed', 'Legacy architecture references remain after migration.', array_values(array_unique($hits)));
    }
}
