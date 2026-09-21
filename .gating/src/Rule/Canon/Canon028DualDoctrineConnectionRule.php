<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon028 dual doctrine connection rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon028DualDoctrineConnectionRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.028.dual_doctrine_connection';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        if (!is_file($context->targetPath.'/bin/console') || !is_file($context->targetPath.'/config/bundles.php')) {
            return $this->result('skipped', 'Target is not a standalone Symfony application.');
        }

        $files = array_merge(glob($context->targetPath.'/config/packages/*.yaml') ?: [], glob($context->targetPath.'/config/packages/*.yml') ?: []);
        $text = '';
        foreach ($files as $file) {
            $text .= "\n".(string) file_get_contents($file);
        }
        if (!str_contains($text, 'doctrine:')) {
            return $this->result('skipped', 'Standalone application declares no Doctrine persistence surface.');
        }

        $hasPg = str_contains($text, 'pdo_pgsql');
        $hasSqlite = str_contains($text, 'pdo_sqlite');
        if (!$hasPg && !$hasSqlite) {
            return $this->result('skipped', 'No canonical PostgreSQL/SQLite Doctrine drivers are declared.');
        }

        $hits = [];
        if ($hasPg && !preg_match('/\bdata\s*:/', $text)) {
            $hits[] = 'PostgreSQL persistence requires canonical data connection role.';
        }
        if ($hasSqlite && !preg_match('/\binfra\s*:/', $text)) {
            $hits[] = 'SQLite persistence requires canonical infra connection role.';
        }

        return [] === $hits
            ? $this->result('passed', 'Doctrine connection roles match data/PostgreSQL and infra/SQLite topology.')
            : $this->result('failed', 'Doctrine connection topology violations found.', $hits);
    }
}
