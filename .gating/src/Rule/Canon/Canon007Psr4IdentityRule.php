<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon007 psr4 identity rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon007Psr4IdentityRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.007.psr4_identity';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $base = $this->profileString($context, 'namespace') ?? $this->composerNamespace($context) ?? 'App';
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $contents = file_get_contents($file->getPathname());
            if (false === $contents) {
                continue;
            }
            $decl = $this->declaration($contents);
            $ns = $this->namespace($contents);
            if (null === $decl || null === $ns) {
                continue;
            }
            $relative = $this->relative($context, $file->getPathname());
            $path = substr($relative, 4, -4);
            $parts = explode('/', $path);
            $filename = array_pop($parts);
            $expectedNs = rtrim($base, '\\').([] === $parts ? '' : '\\'.implode('\\', $parts));
            if ($decl !== $filename) {
                $hits[] = $relative.' declares '.$decl.' instead of '.$filename;
            }
            if ($ns !== $expectedNs) {
                $hits[] = $relative.' namespace '.$ns.' expected '.$expectedNs;
            }
        }

        return [] === $hits ? $this->result('passed', 'PSR-4 path, namespace, filename and declaration identities match.') : $this->result('failed', 'PSR-4 identity violations found.', $hits);
    }

    private function composerNamespace(RuleContext $context): ?string
    {
        $composerPath = $context->targetPath.DIRECTORY_SEPARATOR.'composer.json';
        if (!is_file($composerPath)) {
            return null;
        }

        $raw = file_get_contents($composerPath);
        $composer = false === $raw ? null : json_decode($raw, true);
        $psr4 = is_array($composer) ? ($composer['autoload']['psr-4'] ?? []) : [];
        if (!is_array($psr4)) {
            return null;
        }

        foreach ($psr4 as $namespace => $path) {
            $paths = is_array($path) ? $path : [$path];
            foreach ($paths as $candidate) {
                if (is_string($candidate) && 'src/' === str_replace('\\', '/', rtrim($candidate, '/\\').'/')) {
                    return rtrim((string) $namespace, '\\');
                }
            }
        }

        return null;
    }
}
