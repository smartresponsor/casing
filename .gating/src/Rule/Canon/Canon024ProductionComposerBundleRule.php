<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon024 production composer bundle rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon024ProductionComposerBundleRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.024.production_composer_bundle';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $devPath = $context->targetPath.'/composer.json';
        if (!is_file($devPath)) {
            return $this->result('skipped', 'Target has no composer.json.');
        }

        $dev = json_decode((string) file_get_contents($devPath), true);
        $psr4 = is_array($dev) ? ($dev['autoload']['psr-4'] ?? null) : null;
        if (!is_array($psr4) || [] === array_filter(array_keys($psr4), static fn (mixed $key): bool => is_string($key) && str_starts_with($key, 'App\\'))) {
            return $this->result('skipped', 'Target is not a canonical App\\ component package.');
        }

        $prodPath = $context->targetPath.'/composer.prod.json';
        if (!is_file($prodPath)) {
            return $this->result('failed', 'Canonical SmartResponsor component is missing composer.prod.json.');
        }

        $prod = json_decode((string) file_get_contents($prodPath), true);
        if (!is_array($prod)) {
            return $this->result('failed', 'composer.prod.json is invalid.');
        }

        $hits = [];
        foreach (($prod['repositories'] ?? []) as $repository) {
            if (is_array($repository) && ('path' === ($repository['type'] ?? null) || true === ($repository['options']['symlink'] ?? null))) {
                $hits[] = 'composer.prod.json contains a path/symlink repository.';
            }
        }

        return [] === $hits ? $this->result('passed', 'Production Composer manifest uses packaged dependencies.') : $this->result('failed', 'Production Composer dependency policy violations found.', $hits);
    }
}
