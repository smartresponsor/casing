<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon034 gitignore baseline rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon034GitignoreBaselineRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.034.gitignore_baseline';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $path = $context->targetPath.'/.gitignore';
        if (!is_file($path)) {
            return $this->result('failed', 'Repository has no .gitignore.');
        }

        $text = strtolower(str_replace('\\', '/', (string) file_get_contents($path)));
        $categories = [
            'dependency tree' => ['/vendor/', 'vendor/'],
            'runtime/cache' => ['/var/', '/var/cache', 'var/cache'],
            'local environment' => ['.env.local', '.env.*.local'],
            'quality/test cache' => ['.phpunit', '.php-cs-fixer.cache', 'phpstan'],
            'IDE state' => ['.idea/', '.vscode/'],
            'OS noise' => ['.ds_store', 'thumbs.db'],
        ];
        if (is_file($context->targetPath.'/package.json')) {
            $categories['Node dependency tree'] = ['node_modules'];
        }

        $missing = [];
        foreach ($categories as $category => $patterns) {
            if (!$this->containsAny($text, $patterns)) {
                $missing[] = 'Missing ignore coverage for '.$category.'.';
            }
        }

        return [] === $missing
            ? $this->result('passed', '.gitignore covers the canonical repository-noise baseline.')
            : $this->result('warning', '.gitignore baseline is incomplete.', $missing, 'warning');
    }

    /** @param list<string> $patterns */
    private function containsAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($text, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }
}
