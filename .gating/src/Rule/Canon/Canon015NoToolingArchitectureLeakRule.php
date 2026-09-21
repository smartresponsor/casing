<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon015 no tooling architecture leak rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon015NoToolingArchitectureLeakRule extends AbstractCanonRule
{
    private const array TOOL_ROOTS = ['tool', 'tools', 'script', 'scripts'];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.015.no_tooling_architecture_leak';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hits = [];
        foreach (self::TOOL_ROOTS as $root) {
            $path = $context->targetPath.DIRECTORY_SEPARATOR.$root;
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                    continue;
                }
                if (str_ends_with($file->getFilename(), '.stub.php') || str_contains(str_replace('\\', '/', $file->getPathname()), '/stubs/')) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());
                if (1 === preg_match('/^namespace\s+[^;]+;/m', $contents) && 1 === preg_match('/\b(?:class|interface|trait|enum)\s+\w+/', $contents)) {
                    $hits[] = $this->relative($context, $file->getPathname()).' declares a reusable namespaced PHP type in tooling.';
                }
            }
        }

        return [] === $hits
            ? $this->result('passed', 'No shadow runtime type tree was detected under tooling roots.')
            : $this->result('warning', 'Namespaced reusable types under tooling roots require architecture review.', $hits, 'warning');
    }
}
