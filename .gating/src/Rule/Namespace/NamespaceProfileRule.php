<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Namespace;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the namespace profile rule implementation used by the Gating runtime and rule execution flow.
 */
final class NamespaceProfileRule implements RuleInterface
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'namespace.profile_match';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $expected = $context->profile['component']['namespace'] ?? $context->profile['namespace'] ?? null;
        if (!is_string($expected) || '' === $expected) {
            return new RuleResult($this->id(), 'skipped', 'No component namespace is declared in profile.');
        }

        $src = $context->targetPath.DIRECTORY_SEPARATOR.'src';
        if (!is_dir($src)) {
            return new RuleResult($this->id(), 'skipped', 'Target has no src/ directory.');
        }

        $hits = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (false === $contents) {
                continue;
            }

            if (!preg_match('/^namespace\s+([^;]+);/m', $contents, $match)) {
                continue;
            }

            $namespace = trim($match[1]);
            if ($namespace !== $expected && !str_starts_with($namespace, $expected.'\\')) {
                $hits[] = $this->relativePath($context->targetPath, $file->getPathname()).' => '.$namespace;
            }
        }

        if ([] !== $hits) {
            return new RuleResult($this->id(), 'failed', sprintf('PHP namespaces must stay under %s.', $expected), $hits);
        }

        return new RuleResult($this->id(), 'passed', sprintf('All discovered PHP namespaces stay under %s.', $expected));
    }

    /**
     * Executes the relative path responsibility defined by this Gating component.
     */
    private function relativePath(string $root, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    }
}
