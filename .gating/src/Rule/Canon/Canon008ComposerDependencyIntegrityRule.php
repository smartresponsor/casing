<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon008 composer dependency integrity rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon008ComposerDependencyIntegrityRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.008.composer_dependency_integrity';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $map = $context->profile['component']['namespace_packages'] ?? $context->profile['namespace_packages'] ?? [];
        if (!is_array($map) || [] === $map) {
            return $this->result('skipped', 'No namespace_packages map declared in profile.');
        }
        $composerPath = $context->targetPath.'/composer.json';
        if (!is_file($composerPath)) {
            return $this->result('skipped', 'Target has no composer.json.');
        }
        $composer = json_decode((string) file_get_contents($composerPath), true);
        if (!is_array($composer)) {
            return $this->result('failed', 'composer.json is invalid.');
        }
        $required = array_merge(is_array($composer['require'] ?? null) ? $composer['require'] : [], is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : []);
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            foreach ($map as $namespace => $package) {
                if (!is_string($namespace) || !is_string($package)) {
                    continue;
                }
                if (str_contains($contents, $namespace.'\\') && !array_key_exists($package, $required)) {
                    $hits[] = $this->relative($context, $file->getPathname()).' imports '.$namespace.' without Composer package '.$package;
                }
            }
        }

        return [] === $hits ? $this->result('passed', 'Foreign namespace imports match Composer dependencies.') : $this->result('failed', 'Foreign imports without declared Composer dependencies found.', array_values(array_unique($hits)));
    }
}
