<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon032 bundle registration rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon032BundleRegistrationRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.032.bundle_registration';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $kernelPath = is_file($context->targetPath.'/app/Kernel.php')
            ? $context->targetPath.'/app/Kernel.php'
            : $context->targetPath.'/src/Kernel.php';
        if (!is_file($context->targetPath.'/bin/console') || !is_file($kernelPath)) {
            return $this->result('skipped', 'Target is not a standalone Symfony application.');
        }

        $bundleFiles = glob($context->targetPath.'/src/*Bundle.php') ?: [];
        if ([] === $bundleFiles) {
            return $this->result('failed', 'Standalone component has no reusable src/*Bundle.php surface.');
        }

        $registrationText = (string) file_get_contents($kernelPath);
        $configPath = $context->targetPath.'/config';
        if (is_dir($configPath)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($configPath, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && 'php' === $file->getExtension()) {
                    $registrationText .= "\n".(string) file_get_contents($file->getPathname());
                }
            }
        }

        $hits = [];
        foreach ($bundleFiles as $bundleFile) {
            $contents = (string) file_get_contents($bundleFile);
            $namespace = $this->namespace($contents);
            $class = $this->declaration($contents);
            if (null === $namespace || null === $class) {
                $hits[] = $this->relative($context, $bundleFile).' has no resolvable bundle class identity.';
                continue;
            }

            $fqcn = $namespace.'\\'.$class;
            if (!str_contains($registrationText, $fqcn) && !str_contains($registrationText, $class.'::class')) {
                $hits[] = $fqcn.' is not registered by the standalone bundle configuration.';
            }
        }

        return [] === $hits
            ? $this->result('passed', 'Reusable component bundle surfaces are registered by the standalone application.')
            : $this->result('failed', 'Reusable bundle surface is not fully registered in standalone mode.', $hits);
    }
}
