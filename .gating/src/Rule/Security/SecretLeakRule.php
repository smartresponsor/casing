<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Security;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the secret leak rule implementation used by the Gating runtime and rule execution flow.
 */
final class SecretLeakRule implements RuleInterface
{
    /** @var array<string, array{pattern:string,confidence:string}> */
    private const array SECRET_PATTERNS = [
        'private-key-block' => ['pattern' => '/-----BEGIN (?:RSA |OPENSSH |EC |DSA |PGP )?PRIVATE KEY-----/', 'confidence' => 'high'],
        'aws-access-key' => ['pattern' => '/\bAKIA[0-9A-Z]{16}\b/', 'confidence' => 'high'],
        'github-token' => ['pattern' => '/\bgh[pousr]_[A-Za-z0-9_]{36,}\b/', 'confidence' => 'high'],
        'openai-token' => ['pattern' => '/\bsk-(?:proj-)?[A-Za-z0-9_-]{20,}\b/', 'confidence' => 'high'],
        'slack-token' => ['pattern' => '/\bxox[baprs]-[A-Za-z0-9-]{20,}\b/', 'confidence' => 'high'],
        'generic-secret-assignment' => ['pattern' => '/\b(?:password|passwd|secret|api[_-]?key|access[_-]?token|private[_-]?key)\s*[:=]\s*["\']([^"\'\r\n]{12,})["\']/i', 'confidence' => 'low'],
    ];

    /** @var list<string> */
    private const array SCANNED_EXTENSIONS = [
        'php', 'yaml', 'yml', 'json', 'env', 'ini', 'xml', 'md', 'txt', 'ps1', 'sh', 'bat', 'cmd', 'js', 'ts', 'py', 'neon', 'dist', 'example',
    ];

    /** @var list<string> */
    private const array DEFAULT_EXCLUDED_PATHS = [
        '.git/**',
        'vendor/**',
        'node_modules/**',
        'var/**',
        'cache/**',
        'report/**',
        'evidence/**',
        'public/bundles/**',
        'public/build/**',
        'assets/vendor/**',
    ];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'security.secret_leak';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $configuration = $context->profile['component']['secret_scan'] ?? $context->profile['secret_scan'] ?? 'enabled';
        if (in_array($configuration, ['disabled', 'off', 'false'], true)) {
            return new RuleResult($this->id(), 'skipped', 'Secret leak scan is disabled by profile.');
        }

        $excludedPaths = self::DEFAULT_EXCLUDED_PATHS;
        $declaredExclusions = $context->profile['component']['secret_scan_excluded_paths']
            ?? $context->profile['secret_scan_excluded_paths']
            ?? null;
        if (is_array($declaredExclusions)) {
            $excludedPaths = array_values(array_unique(array_merge($excludedPaths, array_values(array_filter($declaredExclusions, is_string(...))))));
        }

        $hits = [];
        $scanned = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($context->targetPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $relative = $this->relativePath($context->targetPath, $file->getPathname());
            if ($this->isExcluded($relative, $excludedPaths) || !$this->shouldScan($file)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (false === $contents || '' === $contents) {
                continue;
            }

            ++$scanned;
            foreach (preg_split('/\R/', $contents) ?: [] as $lineIndex => $line) {
                foreach (self::SECRET_PATTERNS as $label => $definition) {
                    if (1 !== preg_match($definition['pattern'], $line, $match)) {
                        continue;
                    }

                    $candidate = 'generic-secret-assignment' === $label ? ($match[1] ?? '') : $match[0];
                    if ($this->isEnvironmentReference($candidate)) {
                        continue;
                    }

                    $hits[] = sprintf(
                        '%s:%d contains possible %s (%s confidence): %s',
                        $relative,
                        $lineIndex + 1,
                        $label,
                        $definition['confidence'],
                        $this->redact('' !== $candidate ? $candidate : $match[0]),
                    );
                    break 2;
                }
            }
        }

        if ([] !== $hits) {
            return new RuleResult($this->id(), 'failed', 'Possible secret material was found in repository files.', $hits);
        }

        return new RuleResult($this->id(), 'passed', sprintf('No obvious secret material was found (%d file(s) scanned).', $scanned));
    }

    /**
     * Executes the should scan responsibility defined by this Gating component.
     */
    private function shouldScan(\SplFileInfo $file): bool
    {
        $basename = strtolower($file->getBasename());
        if (str_starts_with($basename, '.env')) {
            return true;
        }

        return in_array(strtolower($file->getExtension()), self::SCANNED_EXTENSIONS, true);
    }

    /** @param list<string> $patterns */
    private function isExcluded(string $relativePath, array $patterns): bool
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
     * Determines whether environment reference satisfies the current Gating condition.
     */
    private function isEnvironmentReference(string $value): bool
    {
        $value = trim($value);

        return 1 === preg_match('/^(?:%env\([^)]*\)%|%[A-Za-z_][A-Za-z0-9_.]*%|\$\{[^}]+\}|\$env:[A-Za-z_][A-Za-z0-9_]*|getenv\s*\(|env\s*\()/i', $value);
    }

    /**
     * Executes the relative path responsibility defined by this Gating component.
     */
    private function relativePath(string $root, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    }

    /**
     * Executes the redact responsibility defined by this Gating component.
     */
    private function redact(string $value): string
    {
        $value = trim($value);
        if (strlen($value) <= 8) {
            return '[redacted]';
        }

        return substr($value, 0, 3).'…'.substr($value, -3);
    }
}
