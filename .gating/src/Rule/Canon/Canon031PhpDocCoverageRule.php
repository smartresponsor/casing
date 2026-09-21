<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon031 php doc coverage rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon031PhpDocCoverageRule extends AbstractCanonRule
{
    private const float THRESHOLD = 70.0;
    private const int MAX_EVIDENCE = 25;

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.031.phpdoc_coverage';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $classTotal = 0;
        $classCovered = 0;
        $methodTotal = 0;
        $methodCovered = 0;
        $methodExcluded = 0;
        $evidence = [];

        foreach ($this->phpFiles($context) as $file) {
            $relative = $this->relative($context, $file->getPathname());
            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match_all('/(?:(\/\*\*.*?\*\/)\s*)?(?:#\[[^\r\n]*\]\s*)*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/s', $contents, $classes, PREG_SET_ORDER)) {
                foreach ($classes as $match) {
                    ++$classTotal;
                    $status = $this->classify($match[1]);
                    if ('covered' === $status) {
                        ++$classCovered;
                    } elseif (count($evidence) < self::MAX_EVIDENCE) {
                        $evidence[] = sprintf('%s::%s [class:%s]', $relative, $match[2], $status);
                    }
                }
            }

            if (preg_match_all('/(?:(\/\*\*.*?\*\/)\s*)?(?:#\[[^\r\n]*\]\s*)*((?:(?:public|protected|private|static|final|abstract)\s+)*)function\s+&?\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/s', $contents, $methods, PREG_SET_ORDER)) {
                foreach ($methods as $match) {
                    if (!$this->isMethodEligible($match[2], $match[3])) {
                        ++$methodExcluded;
                        continue;
                    }
                    ++$methodTotal;
                    $status = $this->classify($match[1]);
                    if ('covered' === $status) {
                        ++$methodCovered;
                    } elseif (count($evidence) < self::MAX_EVIDENCE) {
                        $evidence[] = sprintf('%s::%s() [method:%s]', $relative, $match[3], $status);
                    }
                }
            }
        }

        if (0 === $classTotal && 0 === $methodTotal) {
            return $this->result('skipped', 'No class-like declarations or named methods were discovered.');
        }

        $classCoverage = 0 === $classTotal ? 100.0 : 100.0 * $classCovered / $classTotal;
        $methodCoverage = 0 === $methodTotal ? 100.0 : 100.0 * $methodCovered / $methodTotal;
        $summary = sprintf(
            'PHPDoc coverage: classes %d/%d (%.1f%%), contract methods %d/%d (%.1f%%), excluded trivial/internal methods %d; threshold %.0f%%.',
            $classCovered,
            $classTotal,
            $classCoverage,
            $methodCovered,
            $methodTotal,
            $methodCoverage,
            $methodExcluded,
            self::THRESHOLD,
        );

        if ($classCoverage >= self::THRESHOLD && $methodCoverage >= self::THRESHOLD) {
            return $this->result('passed', $summary);
        }

        array_unshift($evidence, 'Semantic PHPDoc review and documentation completion are required.');

        return $this->result('warning', $summary, $evidence, 'warning');
    }

    /**
     * Keeps the documentation denominator focused on externally meaningful behavior rather than implementation noise.
     */
    private function isMethodEligible(string $modifiers, string $name): bool
    {
        if (1 === preg_match('/\bprivate\b/', $modifiers) || str_starts_with($name, '__')) {
            return false;
        }

        return 1 !== preg_match('/^(?:get|set|is|has)[A-Z_]/', $name);
    }

    /**
     * Executes the classify responsibility defined by this Gating component.
     */
    private function classify(string $docBlock): string
    {
        if ('' === trim($docBlock)) {
            return 'missing';
        }

        $descriptionLines = [];
        foreach (preg_split('/\R/', $docBlock) ?: [] as $line) {
            $line = trim($line);
            $line = preg_replace('/^\/\*\*|\*\/$/', '', $line) ?? $line;
            $line = trim(preg_replace('/^\*\s?/', '', $line) ?? $line);
            if ('' === $line || str_starts_with($line, '@')) {
                continue;
            }
            $descriptionLines[] = $line;
        }

        if ([] === $descriptionLines) {
            return 'tags_only';
        }

        $description = trim(implode(' ', $descriptionLines));
        $normalized = strtolower(trim($description, " .:\t\n\r\0\x0B"));
        if (in_array($normalized, ['todo', 'tbd', 'description', 'method description', 'class description', 'getter', 'setter', 'constructor', 'returns value'], true)) {
            return 'placeholder';
        }

        $words = preg_split('/\s+/', $description) ?: [];
        if (strlen($description) < 30 || count(array_filter($words, static fn (string $word): bool => '' !== trim($word))) < 5) {
            return 'too_short';
        }

        return 'covered';
    }
}
