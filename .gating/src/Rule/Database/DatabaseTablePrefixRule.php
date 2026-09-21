<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Database;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the database table prefix rule implementation used by the Gating runtime and rule execution flow.
 */
final class DatabaseTablePrefixRule implements RuleInterface
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'database.table_prefix';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $prefix = $context->profile['component']['database_prefix'] ?? $context->profile['database_prefix'] ?? null;
        if (!is_string($prefix) || '' === $prefix) {
            return new RuleResult($this->id(), 'skipped', 'No database_prefix is declared in profile.');
        }

        $src = $context->targetPath.DIRECTORY_SEPARATOR.'src';
        if (!is_dir($src)) {
            return new RuleResult($this->id(), 'skipped', 'Target has no src/ directory.');
        }

        $hits = [];
        $tables = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (false === $contents) {
                continue;
            }

            foreach ($this->extractTableNames($contents) as $tableName) {
                $tables[] = $tableName;
                if (!str_starts_with($tableName, $prefix)) {
                    $hits[] = $this->relativePath($context->targetPath, $file->getPathname()).' declares table '.$tableName.', expected prefix '.$prefix;
                }
            }
        }

        if ([] === $tables) {
            return new RuleResult($this->id(), 'skipped', 'No Doctrine table declarations were found.');
        }

        if ([] !== $hits) {
            return new RuleResult($this->id(), 'failed', 'Doctrine table names must use the component database prefix.', $hits);
        }

        return new RuleResult($this->id(), 'passed', sprintf('All discovered Doctrine table names use prefix %s.', $prefix));
    }

    /** @return list<string> */
    private function extractTableNames(string $contents): array
    {
        $names = [];

        if (preg_match_all('/#\[ORM\\\\Table\s*\([^\)]*nameEntity\s*:\s*[\'\"]([^\'\"]+)[\'\"]/m', $contents, $matches)) {
            foreach ($matches[1] as $nameEntity) {
                $names[] = $nameEntity;
            }
        }

        if (preg_match_all('/#\[Table\s*\([^\)]*nameEntity\s*:\s*[\'\"]([^\'\"]+)[\'\"]/m', $contents, $matches)) {
            foreach ($matches[1] as $nameEntity) {
                $names[] = $nameEntity;
            }
        }

        if (preg_match_all('/@ORM\\\\Table\s*\([^\)]*nameEntity\s*=\s*[\'\"]([^\'\"]+)[\'\"]/m', $contents, $matches)) {
            foreach ($matches[1] as $nameEntity) {
                $names[] = $nameEntity;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Executes the relative path responsibility defined by this Gating component.
     */
    private function relativePath(string $root, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    }
}
