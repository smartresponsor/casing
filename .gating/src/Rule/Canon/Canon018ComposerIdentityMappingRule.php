<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon018 composer identity mapping rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon018ComposerIdentityMappingRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.018.composer_identity_mapping';
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
        if (!is_array($composer) || !is_string($composer['name'] ?? null)) {
            return $this->result('failed', 'composer.json must declare a valid package name.');
        }

        $tokens = explode('/', $composer['name']);
        if (2 !== count($tokens) || '' === $tokens[0] || '' === $tokens[1]) {
            return $this->result('failed', 'Composer package name must be <component-token>/<subject-token>.');
        }

        $psr4 = $composer['autoload']['psr-4'] ?? [];
        if (!is_array($psr4)) {
            return $this->result('skipped', 'No PSR-4 mapping is declared.');
        }

        $appMappings = array_filter(array_keys($psr4), static fn (mixed $key): bool => is_string($key) && str_starts_with($key, 'App\\'));
        if ([] === $appMappings) {
            return $this->result('skipped', 'Package does not use the SmartResponsor App\\<Component>\\ namespace model.');
        }

        $component = $this->studly($tokens[0]);
        $subject = $this->studly($tokens[1]);
        $expectedNamespace = 'App\\'.$component.'\\';
        $hits = [];
        if (($psr4[$expectedNamespace] ?? null) !== 'src/') {
            $hits[] = sprintf('composer name %s expects PSR-4 %s => src/.', $composer['name'], $expectedNamespace);
        }

        foreach ($this->phpFiles($context) as $file) {
            $relative = $this->relative($context, $file->getPathname());
            $parts = explode('/', $relative);
            if (count($parts) < 3 || in_array($parts[1], ['DependencyInjection'], true)) {
                continue;
            }
            $declaration = $this->declaration((string) file_get_contents($file->getPathname()));
            if (null !== $declaration && !str_starts_with($declaration, $subject)) {
                $hits[] = $relative.' declares '.$declaration.'; expected subject prefix '.$subject.'.';
            }
        }

        return [] === $hits ? $this->result('passed', 'Composer component/subject identity matches PSR-4 namespace and PHP subject vocabulary.') : $this->result('failed', 'Composer identity mapping violations found.', $hits);
    }

    /**
     * Executes the studly responsibility defined by this Gating component.
     */
    private function studly(string $token): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($token))));
    }
}
