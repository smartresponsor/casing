<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Mutation;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the mutation safety rule implementation used by the Gating runtime and rule execution flow.
 */
final class MutationSafetyRule implements RuleInterface
{
    /** @var list<string> */
    private const array FILE_NAME_SIGNALS = [
        'cleanup',
        'clean',
        'purge',
        'remove',
        'delete',
        'apply',
        'fix',
        'repair',
    ];

    /** @var list<string> */
    private const array DANGEROUS_PATTERNS = [
        '/\brm\s+-rf\s+(\.|\$\{?PWD\}?|\$\{?ROOT\}?|\$\{?TARGET\}?|\*)/i',
        '/\bRemove-Item\b[^\r\n;]*(?:-Recurse|-r)\b[^\r\n;]*(?:-Force|-f)\b/i',
        '/\bdel\s+\/s\s+\/q\b/i',
        '/\brmdir\s+\/s\s+\/q\b/i',
        '/\bgit\s+clean\s+-fdx\b/i',
        '/\bRemove-Item\b[^\r\n;]*\*[^\r\n;]*(?:-Recurse|-r)/i',
    ];

    /** @var list<string> */
    private const array IGNORED_SEGMENTS = [
        '/.git/',
        '/.gating/',
        '/vendor/',
        '/node_modules/',
        '/var/',
        '/cache/',
    ];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'mutation.safety_firewall';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $mode = $context->profile['component']['mutation_mode'] ?? $context->profile['mutation_mode'] ?? 'forbidden';
        if (in_array($mode, ['allowed', 'allow'], true)) {
            return new RuleResult($this->id(), 'skipped', 'Mutation safety firewall is disabled by profile.');
        }

        $excludedPaths = $context->profile['component']['mutation_scan_excluded_paths']
            ?? $context->profile['mutation_scan_excluded_paths']
            ?? [];
        $excludedPaths = is_array($excludedPaths) ? array_values(array_filter($excludedPaths, is_string(...))) : [];

        $hits = [];
        $mutationNamedFiles = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($context->targetPath));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            if ($this->isIgnored($path)) {
                continue;
            }
            if (!in_array($file->getExtension(), ['php', 'ps1', 'sh', 'bat', 'cmd', 'js', 'ts', 'py'], true)) {
                continue;
            }

            $relative = $this->relativePath($context->targetPath, $path);
            if ($this->isProfileExcluded($relative, $excludedPaths)) {
                continue;
            }
            if ($this->nameSuggestsMutation($file->getBasename())) {
                ++$mutationNamedFiles;
            }

            $contents = file_get_contents($file->getPathname());
            if (false === $contents) {
                continue;
            }

            foreach (self::DANGEROUS_PATTERNS as $pattern) {
                if (preg_match($pattern, $contents, $match)) {
                    $hits[] = sprintf('%s contains dangerous mutation pattern: %s', $relative, trim($match[0]));
                    break;
                }
            }
        }

        if ([] !== $hits) {
            return new RuleResult($this->id(), 'failed', 'Dangerous broad mutation scripts were found. Default Gating mode is report/plan/diff only.', $hits);
        }

        if ($mutationNamedFiles > 0) {
            return new RuleResult($this->id(), 'passed', sprintf('No broad destructive mutation pattern was found (%d mutation-named file(s) observed).', $mutationNamedFiles));
        }

        return new RuleResult($this->id(), 'passed', 'No broad destructive mutation pattern was found.');
    }

    /**
     * Executes the name suggests mutation responsibility defined by this Gating component.
     */
    private function nameSuggestsMutation(string $basename): bool
    {
        $lower = strtolower($basename);

        return array_any(self::FILE_NAME_SIGNALS, fn ($signal) => str_contains($lower, $signal));
    }

    /** @param list<string> $patterns */
    private function isProfileExcluded(string $relativePath, array $patterns): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        foreach ($patterns as $pattern) {
            $pattern = ltrim(str_replace('\\', '/', $pattern), '/');
            if (function_exists('fnmatch') && fnmatch($pattern, $relativePath, FNM_PATHNAME)) {
                return true;
            }
            $prefix = rtrim(str_replace('/**', '', $pattern), '/');
            if ('' !== $prefix && ($relativePath === $prefix || str_starts_with($relativePath, $prefix.'/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether ignored satisfies the current Gating condition.
     */
    private function isIgnored(string $path): bool
    {
        $normalized = '/'.trim($path, '/').'/';

        return array_any(self::IGNORED_SEGMENTS, fn ($ignored) => str_contains($normalized, $ignored));
    }

    /**
     * Executes the relative path responsibility defined by this Gating component.
     */
    private function relativePath(string $root, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    }
}
