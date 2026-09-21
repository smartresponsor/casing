<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Documentation;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the docblock preservation rule implementation used by the Gating runtime and rule execution flow.
 */
final readonly class DocblockPreservationRule implements RuleInterface
{
    /** @var list<string> */
    private const array RISKY_RECTOR_CLASSES = [
        'GeneralizePrecisionOfTypesRector',
        'RemoveUselessDocBlockRector',
        'RemoveEmptyDocBlockRector',
    ];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'documentation.docblock_preservation';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $component = is_array($context->profile['component'] ?? null) ? $context->profile['component'] : [];
        if (($component['docblock_preservation'] ?? 'enabled') === 'disabled') {
            return new RuleResult($this->id(), 'skipped', 'Docblock preservation check is disabled by component profile.');
        }

        $violations = [];
        foreach ($this->candidateFiles($context->targetPath) as $path) {
            $contents = file_get_contents($path);
            if (false === $contents) {
                continue;
            }

            $basename = basename($path);
            if (in_array($basename, ['.php-cs-fixer.php', '.php-cs-fixer.dist.php', 'ecs.php'], true)) {
                foreach (['phpdoc_to_comment', 'no_superfluous_phpdoc_tags'] as $ruleName) {
                    $value = $this->configuredBoolean($contents, $ruleName);
                    if (true === $value) {
                        $violations[] = sprintf('%s enables risky docblock configuration: %s', $this->relative($context->targetPath, $path), $ruleName);
                    }
                }
            }

            if ('rector.php' === $basename) {
                foreach (self::RISKY_RECTOR_CLASSES as $className) {
                    if (1 === preg_match('/\b'.preg_quote($className, '/').'\b/', $contents)) {
                        $violations[] = sprintf('%s enables risky docblock Rector: %s', $this->relative($context->targetPath, $path), $className);
                    }
                }
            }
        }

        if ([] !== $violations) {
            return new RuleResult($this->id(), 'failed', 'Risky docblock-stripping configuration was found.', array_values(array_unique($violations)));
        }

        return new RuleResult($this->id(), 'passed', 'No enabled risky docblock-stripping configuration was found.');
    }

    /**
     * Executes the configured boolean responsibility defined by this Gating component.
     */
    private function configuredBoolean(string $contents, string $ruleName): ?bool
    {
        $pattern = '/[\'\"]'.preg_quote($ruleName, '/').'[\'\"]\s*=>\s*(true|false)\b/i';
        if (1 !== preg_match($pattern, $contents, $matches)) {
            return null;
        }

        return 'true' === strtolower($matches[1]);
    }

    /** @return iterable<string> */
    private function candidateFiles(string $targetPath): iterable
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($targetPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (str_contains($path, DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR) || str_contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            if (!in_array(basename($path), ['rector.php', '.php-cs-fixer.php', '.php-cs-fixer.dist.php', 'ecs.php'], true)) {
                continue;
            }
            yield $path;
        }
    }

    /**
     * Executes the relative responsibility defined by this Gating component.
     */
    private function relative(string $targetPath, string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen($targetPath) + 1));
    }
}
