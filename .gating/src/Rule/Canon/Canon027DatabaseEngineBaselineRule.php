<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon027 database engine baseline rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon027DatabaseEngineBaselineRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.027.database_engine_baseline';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $files = array_merge(glob($context->targetPath.'/config/packages/*.yaml') ?: [], glob($context->targetPath.'/config/packages/*.yml') ?: []);
        if ([] === $files) {
            return $this->result('skipped', 'No package YAML configuration found.');
        }

        $text = '';
        foreach ($files as $file) {
            $text .= "\n".(string) file_get_contents($file);
        }
        if (!str_contains($text, 'doctrine:')) {
            return $this->result('skipped', 'No Doctrine configuration found.');
        }

        $hits = [];
        foreach (['pdo_mysql', 'pdo_oci', 'pdo_sqlsrv'] as $driver) {
            if (str_contains($text, $driver)) {
                $hits[] = 'Non-canonical relational driver found: '.$driver;
            }
        }

        return [] === $hits
            ? $this->result('passed', 'Doctrine database engines are restricted to PostgreSQL and SQLite.')
            : $this->result('failed', 'Database engine baseline violations found.', $hits);
    }
}
