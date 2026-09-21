<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon030 doctrine schema parity rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon030DoctrineSchemaParityRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.030.doctrine_schema_parity';
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
        if (!is_array($composer)) {
            return $this->result('failed', 'composer.json is invalid.');
        }

        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $hasOrm = array_key_exists('doctrine/orm', $require) || array_key_exists('doctrine/doctrine-bundle', $require);
        $hasMigrations = array_key_exists('doctrine/doctrine-migrations-bundle', $require) || array_key_exists('doctrine/migrations', $require);
        if (!$hasOrm && !$hasMigrations) {
            return $this->result('skipped', 'Target does not own Doctrine ORM/migrations persistence.');
        }

        $hits = [];
        if (!$hasOrm) {
            $hits[] = 'Doctrine migrations are present without Doctrine ORM metadata ownership.';
        }
        if (!$hasMigrations) {
            $hits[] = 'Doctrine ORM persistence is present without Doctrine migrations tooling.';
        }

        $scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
        $scriptText = strtolower(json_encode($scripts, JSON_UNESCAPED_SLASHES) ?: '');
        $hasSchemaValidate = str_contains($scriptText, 'doctrine:schema:validate');
        $hasMigrationCurrentness = str_contains($scriptText, 'doctrine:migrations:up-to-date')
            || str_contains($scriptText, 'doctrine:migrations:diff')
            || str_contains($scriptText, 'schema:parity');

        if (!$hasSchemaValidate) {
            $hits[] = 'Composer/CI scripts do not expose doctrine:schema:validate.';
        }
        if (!$hasMigrationCurrentness) {
            $hits[] = 'Composer/CI scripts do not expose migration currentness/diff verification.';
        }

        return [] === $hits
            ? $this->result('passed', 'Doctrine ORM/migrations repository exposes an executable schema-parity contract.')
            : $this->result('failed', 'Doctrine Entity/migration schema parity contract is incomplete.', $hits);
    }
}
