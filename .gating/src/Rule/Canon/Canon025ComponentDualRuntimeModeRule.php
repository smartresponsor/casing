<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon025 component dual runtime mode rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon025ComponentDualRuntimeModeRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.025.component_dual_runtime_mode';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $composerPath = $context->targetPath.'/composer.json';
        if (!is_file($composerPath)) {
            return $this->result('skipped', 'Target has no composer.json.');
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);
        $psr4 = is_array($composer) ? ($composer['autoload']['psr-4'] ?? null) : null;
        if (!is_array($psr4) || [] === array_filter(array_keys($psr4), static fn (mixed $key): bool => is_string($key) && str_starts_with($key, 'App\\'))) {
            return $this->result('skipped', 'Target does not use the SmartResponsor App\\ component model.');
        }

        $hits = [];
        foreach (['bin/console', 'config/bundles.php'] as $surface) {
            if (!is_file($context->targetPath.'/'.$surface)) {
                $hits[] = 'Missing standalone Symfony surface '.$surface;
            }
        }

        if ([] === (glob($context->targetPath.'/src/*Bundle.php') ?: [])) {
            $hits[] = 'Missing component bundle class src/*Bundle.php.';
        }

        return [] === $hits
            ? $this->result('passed', 'Component exposes standalone and bundle runtime surfaces.')
            : $this->result('failed', 'Component dual runtime mode is incomplete.', $hits);
    }
}
