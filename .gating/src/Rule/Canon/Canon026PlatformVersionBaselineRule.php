<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon026 platform version baseline rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon026PlatformVersionBaselineRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.026.platform_version_baseline';
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

        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $hits = [];
        $php = $require['php'] ?? null;
        if (!is_string($php) || !preg_match('/8\.[4-9]|9\./', $php)) {
            $hits[] = 'PHP constraint must target PHP 8.4 or newer.';
        }

        foreach ($require as $package => $constraint) {
            if (!is_string($package) || !str_starts_with($package, 'symfony/') || !is_string($constraint)) {
                continue;
            }
            if (in_array($package, ['symfony/flex', 'symfony/monolog-bundle'], true)) {
                continue;
            }
            if (preg_match('/^\\s*(?:[~^]|>=?|<=?)?\\s*(?:[0-7](?:\\.|$)|8\\.0(?:\\.|$))/', $constraint)) {
                $hits[] = $package.' permits Symfony below 8.1: '.$constraint;
            }
            if (preg_match('/(?:^|[^0-9])9\./', $constraint)) {
                $hits[] = $package.' permits Symfony 9.x; platform baseline is Symfony 8.x.';
            }
        }

        $extraSymfony = $composer['extra']['symfony']['require'] ?? null;
        if (is_string($extraSymfony)) {
            if (preg_match('/^\\s*(?:[~^]|>=?|<=?)?\\s*(?:[0-7](?:\\.|$)|8\\.0(?:\\.|$))/', $extraSymfony)) {
                $hits[] = 'extra.symfony.require permits Symfony below 8.1: '.$extraSymfony;
            }
            if (preg_match('/^\\s*(?:[~^]|>=?|<=?)?\\s*9\\./', $extraSymfony)) {
                $hits[] = 'extra.symfony.require permits Symfony 9.x; platform baseline is Symfony 8.x.';
            }
        }

        return [] === $hits
            ? $this->result('passed', 'Composer constraints match PHP 8.4+ and Symfony 8.1+ baseline.')
            : $this->result('failed', 'Platform version baseline violations found.', $hits);
    }
}
