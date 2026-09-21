<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Structure;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the forbidden architecture rule implementation used by the Gating runtime and rule execution flow.
 */
final class ForbiddenArchitectureRule implements RuleInterface
{
    /** @var list<string> */
    private const array DEFAULT_FORBIDDEN_SEGMENTS = [
        'src/Domain',
        '/Port/',
        '/Ports/',
        '/Adapter/',
        '/Adapters/',
        '/Adaptor/',
        '/Adaptors/',
    ];

    /** @var list<string> */
    private const array IGNORED_SEGMENTS = [
        '/.git/',
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
        return 'structure.forbidden_architecture';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hits = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($context->targetPath));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            if ($this->isIgnored($path)) {
                continue;
            }

            $normalized = '/'.ltrim($this->relativePath($context->targetPath, $path), '/');
            foreach (self::DEFAULT_FORBIDDEN_SEGMENTS as $needle) {
                if (str_contains($normalized, $needle)) {
                    $hits[] = ltrim($normalized, '/').' matched '.$needle;
                    break;
                }
            }
        }

        if ([] !== $hits) {
            return new RuleResult($this->id(), 'failed', 'Forbidden architecture folders were found.', $hits);
        }

        return new RuleResult($this->id(), 'passed', 'No forbidden architecture folders were found.');
    }

    /**
     * Determines whether ignored satisfies the current Gating condition.
     */
    private function isIgnored(string $path): bool
    {
        $normalized = '/'.trim(str_replace('\\', '/', $path), '/').'/';

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
