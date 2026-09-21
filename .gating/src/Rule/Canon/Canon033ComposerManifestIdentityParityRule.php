<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon033 composer manifest identity parity rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon033ComposerManifestIdentityParityRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.033.composer_manifest_identity_parity';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $devPath = $context->targetPath.'/composer.json';
        $prodPath = $context->targetPath.'/composer.prod.json';
        if (!is_file($devPath)) {
            return $this->result('skipped', 'Target has no composer.json.');
        }

        $dev = json_decode((string) file_get_contents($devPath), true);
        if (!is_array($dev) || !$this->usesComponentNamespace($dev)) {
            return $this->result('skipped', 'Target does not use the SmartResponsor App\\ component identity model.');
        }
        if (!is_file($prodPath)) {
            return $this->result('skipped', 'composer.prod.json existence is owned by Canon024.');
        }

        $prod = json_decode((string) file_get_contents($prodPath), true);
        if (!is_array($prod)) {
            return $this->result('failed', 'composer.prod.json is invalid JSON.');
        }

        $hits = [];
        foreach (['name', 'type'] as $key) {
            if (($dev[$key] ?? null) !== ($prod[$key] ?? null)) {
                $hits[] = sprintf('%s differs between development and production manifests.', $key);
            }
        }

        if (($dev['autoload']['psr-4'] ?? []) !== ($prod['autoload']['psr-4'] ?? [])) {
            $hits[] = 'autoload.psr-4 differs between development and production manifests.';
        }
        if ($this->baseline($dev['require']['php'] ?? null) !== $this->baseline($prod['require']['php'] ?? null)) {
            $hits[] = 'PHP baseline differs between development and production manifests.';
        }
        if ($this->symfonyBaseline($dev) !== $this->symfonyBaseline($prod)) {
            $hits[] = 'Symfony baseline differs between development and production manifests.';
        }

        return [] === $hits
            ? $this->result('passed', 'Development and production Composer manifests describe the same component identity.')
            : $this->result('failed', 'Composer manifest identity parity is broken.', $hits);
    }

    /** @param array<string, mixed> $composer */
    private function usesComponentNamespace(array $composer): bool
    {
        $psr4 = $composer['autoload']['psr-4'] ?? [];
        if (!is_array($psr4)) {
            return false;
        }
        foreach ($psr4 as $namespace => $_path) {
            if (is_string($namespace) && str_starts_with($namespace, 'App\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Executes the baseline responsibility defined by this Gating component.
     */
    private function baseline(mixed $constraint): ?string
    {
        return is_string($constraint) && 1 === preg_match('/(\d+)\.(\d+)/', $constraint, $m) ? $m[1].'.'.$m[2] : null;
    }

    /** @param array<string, mixed> $composer */
    private function symfonyBaseline(array $composer): ?string
    {
        $extra = $composer['extra']['symfony']['require'] ?? null;
        if (is_string($extra)) {
            return $this->baseline($extra);
        }
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        if (isset($require['symfony/framework-bundle'])) {
            return $this->baseline($require['symfony/framework-bundle']);
        }
        foreach ($require as $package => $constraint) {
            if (is_string($package) && str_starts_with($package, 'symfony/')) {
                return $this->baseline($constraint);
            }
        }

        return null;
    }
}
