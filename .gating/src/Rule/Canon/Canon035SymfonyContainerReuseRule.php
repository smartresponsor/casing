<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon035 symfony container reuse rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon035SymfonyContainerReuseRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.035.symfony_container_reuse';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $composerPath = $context->targetPath.'/composer.json';
        $kernelPath = is_file($context->targetPath.'/app/Kernel.php')
            ? $context->targetPath.'/app/Kernel.php'
            : $context->targetPath.'/src/Kernel.php';
        if (!is_file($composerPath) || !is_file($kernelPath)) {
            return $this->result('skipped', 'Target is not a standalone Symfony kernel application.');
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        if (!array_key_exists('symfony/framework-bundle', $require)) {
            return $this->result('skipped', 'Target does not use Symfony FrameworkBundle.');
        }

        $hits = [];
        $warnings = [];
        foreach ($this->runtimePhpFiles($context) as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            $relative = $this->relative($context, $file->getPathname());
            foreach (['cache:clear', 'cache:warmup', '->reboot('] as $needle) {
                if (str_contains($contents, $needle)) {
                    $hits[] = $relative.' contains request/runtime container invalidation token '.$needle;
                }
            }
            if (1 === preg_match('/new\s+Kernel\s*\(\s*[\'\"]dev[\'\"]\s*,\s*true\s*\)/i', $contents)) {
                $hits[] = $relative.' hardcodes a dev/debug kernel in runtime code.';
            }
        }

        $kernel = (string) file_get_contents($kernelPath);
        if (str_contains($kernel, 'function getContainerClass(')) {
            $warnings[] = 'Kernel overrides getContainerClass(); verify that container identity is stable and not request/time/random dependent.';
        }
        if (1 === preg_match('/function\s+getCacheDir\s*\([^)]*\).*?(?:request|uniqid|random|microtime|time\s*\()/is', $kernel)) {
            $hits[] = 'Kernel cache directory appears to depend on request/time/random state.';
        }

        if ([] !== $hits) {
            return $this->result('failed', 'Symfony compiled-container reuse invariant is violated.', $hits);
        }
        if ([] !== $warnings) {
            return $this->result('warning', 'Symfony container identity customization requires stability review.', $warnings, 'warning');
        }

        return $this->result('passed', 'No known request-time container rebuild or unstable cache identity pattern was detected.');
    }

    /** @return list<\SplFileInfo> */
    private function runtimePhpFiles(RuleContext $context): array
    {
        $files = $this->phpFiles($context);
        $public = $context->targetPath.'/public';
        if (is_dir($public)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($public, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && 'php' === $file->getExtension()) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }
}
