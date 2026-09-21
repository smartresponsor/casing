<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the abstract canon rule implementation used by the Gating runtime and rule execution flow.
 */
abstract class AbstractCanonRule implements RuleInterface
{
    /** @return list<\SplFileInfo> */
    final protected function phpFiles(RuleContext $context): array
    {
        $src = $context->targetPath.DIRECTORY_SEPARATOR.'src';
        if (!is_dir($src)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && 'php' === $file->getExtension()) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Executes the relative responsibility defined by this Gating component.
     */
    final protected function relative(RuleContext $context, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($context->targetPath))), '/');
    }

    /**
     * Resolves the first named PHP type declaration while ignoring comments and anonymous classes.
     */
    final protected function declaration(string $contents): ?string
    {
        $tokens = token_get_all($contents);
        $expectName = false;
        $previousSignificant = null;

        foreach ($tokens as $token) {
            if (!is_array($token)) {
                if ($expectName && '{' === $token) {
                    $expectName = false;
                }
                if (!ctype_space($token)) {
                    $previousSignificant = $token;
                }
                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                if (T_CLASS === $token[0] && T_DOUBLE_COLON === $previousSignificant) {
                    $previousSignificant = $token[0];
                    continue;
                }

                $expectName = true;
                $previousSignificant = $token[0];
                continue;
            }

            if ($expectName && T_STRING === $token[0]) {
                return $token[1];
            }

            $previousSignificant = $token[0];
        }

        return null;
    }

    /**
     * Executes the namespace responsibility defined by this Gating component.
     */
    final protected function namespace(string $contents): ?string
    {
        return 1 === preg_match('/^namespace\s+([^;]+);/m', $contents, $m) ? trim($m[1]) : null;
    }

    /**
     * Executes the profile string responsibility defined by this Gating component.
     */
    final protected function profileString(RuleContext $context, string $key): ?string
    {
        $value = $context->profile['component'][$key] ?? $context->profile[$key] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /** @return list<string> */
    final protected function profileList(RuleContext $context, string $key): array
    {
        $value = $context->profile['component'][$key] ?? $context->profile[$key] ?? [];
        if (is_string($value) && '' !== trim($value)) {
            return [trim($value)];
        }

        return is_array($value) ? array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && '' !== trim($item))) : [];
    }

    /** @param list<string> $evidence */
    final protected function result(string $status, string $message, array $evidence = [], string $severity = 'error'): RuleResult
    {
        return new RuleResult($this->id(), $status, $message, $evidence, $severity);
    }
}
