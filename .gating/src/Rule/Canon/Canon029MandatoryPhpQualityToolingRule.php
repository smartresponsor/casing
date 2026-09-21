<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon029 mandatory php quality tooling rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon029MandatoryPhpQualityToolingRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.029.mandatory_php_quality_tooling';
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
        $autoload = is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];
        if (!array_key_exists('php', $require) && [] === $autoload) {
            return $this->result('skipped', 'Target does not look like a PHP package.');
        }

        $requireDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];
        $scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
        $hits = [];

        foreach (['friendsofphp/php-cs-fixer', 'phpstan/phpstan'] as $package) {
            if (!array_key_exists($package, $requireDev)) {
                $hits[] = 'composer.json:require-dev is missing '.$package;
            }
        }

        if (!is_file($context->targetPath.'/.php-cs-fixer.dist.php') && !is_file($context->targetPath.'/.php-cs-fixer.php')) {
            $hits[] = 'Missing PHP-CS-Fixer config.';
        }

        $phpStanConfig = false;
        foreach (['phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon'] as $file) {
            if (is_file($context->targetPath.'/'.$file)) {
                $phpStanConfig = true;
                break;
            }
        }
        if (!$phpStanConfig && [] !== (glob($context->targetPath.'/.gating/quality/*/php/phpstan.neon') ?: [])) {
            $phpStanConfig = true;
        }
        if (!$phpStanConfig) {
            $hits[] = 'Missing PHPStan config.';
        }

        $scriptText = strtolower(json_encode($scripts, JSON_UNESCAPED_SLASHES) ?: '');
        if (!str_contains($scriptText, 'php-cs-fixer')) {
            $hits[] = 'Composer scripts do not expose PHP-CS-Fixer.';
        }
        if (!str_contains($scriptText, 'phpstan')) {
            $hits[] = 'Composer scripts do not expose PHPStan.';
        }

        return [] === $hits
            ? $this->result('passed', 'PHP-CS-Fixer and PHPStan are installed, configured, and executable through Composer scripts.')
            : $this->result('failed', 'Mandatory PHP quality tooling contract is incomplete.', $hits);
    }
}
