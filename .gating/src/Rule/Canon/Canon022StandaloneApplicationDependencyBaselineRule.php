<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon022 standalone application dependency baseline rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon022StandaloneApplicationDependencyBaselineRule extends AbstractCanonRule
{
    private const array REQUIRED = [
        'cruding/crud',
        'collectioning/collection',
        'tabling/table',
        'viewing/view',
        'interfacing/interface',
        'objecting/object',
        'easycorp/easyadmin-bundle',
    ];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.022.standalone_application_dependency_baseline';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        if (!is_file($context->targetPath.'/bin/console') || !is_file($context->targetPath.'/config/bundles.php')) {
            return $this->result('skipped', 'Target does not expose both bin/console and config/bundles.php standalone Symfony boot surfaces.');
        }

        $composerPath = $context->targetPath.'/composer.json';
        $composer = is_file($composerPath) ? json_decode((string) file_get_contents($composerPath), true) : null;
        if (!is_array($composer)) {
            return $this->result('failed', 'Standalone Symfony application has no valid composer.json.');
        }

        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $missing = [];
        foreach (self::REQUIRED as $package) {
            if (!array_key_exists($package, $require)) {
                $missing[] = 'composer.json:require is missing direct runtime dependency '.$package;
            }
        }

        return [] === $missing
            ? $this->result('passed', 'Standalone Symfony application declares the complete SmartResponsor platform dependency baseline.')
            : $this->result('failed', 'Standalone Symfony application dependency baseline is incomplete.', $missing);
    }
}
