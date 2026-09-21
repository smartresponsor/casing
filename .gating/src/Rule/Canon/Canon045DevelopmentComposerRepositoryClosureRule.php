<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Enforces root visibility of the complete local first-party Composer repository closure.
 */
final class Canon045DevelopmentComposerRepositoryClosureRule extends AbstractCanonRule
{
    public function id(): string
    {
        return 'canon.045.development_composer_repository_closure';
    }

    public function check(RuleContext $context): RuleResult
    {
        $composerPath = $context->targetPath.'/composer.json';
        if (!is_file($composerPath)) {
            return $this->result('skipped', 'Target has no composer.json.');
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);
        if (!is_array($composer)) {
            return $this->result('failed', 'composer.json is invalid.');
        }

        $rootPaths = [];
        $queue = [];
        foreach (($composer['repositories'] ?? []) as $repository) {
            if (!is_array($repository) || 'path' !== ($repository['type'] ?? null)) {
                continue;
            }

            $url = $repository['url'] ?? null;
            if (!is_string($url) || !str_starts_with(str_replace('\\', '/', $url), '../')) {
                continue;
            }

            $resolved = realpath($context->targetPath.DIRECTORY_SEPARATOR.$url);
            if (false === $resolved) {
                continue;
            }

            $rootPaths[$this->normalizePath($resolved)] = $url;
            $queue[] = $resolved;
        }

        if ([] === $queue) {
            return $this->result('passed', 'No local sibling Composer path repositories require closure expansion.');
        }

        $visited = [];
        $hits = [];
        while ([] !== $queue) {
            $packagePath = array_shift($queue);
            if (!is_string($packagePath)) {
                continue;
            }

            $normalizedPackagePath = $this->normalizePath($packagePath);
            if (isset($visited[$normalizedPackagePath])) {
                continue;
            }
            $visited[$normalizedPackagePath] = true;

            $dependencyComposerPath = $packagePath.DIRECTORY_SEPARATOR.'composer.json';
            if (!is_file($dependencyComposerPath)) {
                continue;
            }

            $dependencyComposer = json_decode((string) file_get_contents($dependencyComposerPath), true);
            if (!is_array($dependencyComposer)) {
                continue;
            }

            $runtimeRequire = is_array($dependencyComposer['require'] ?? null) ? $dependencyComposer['require'] : [];
            foreach (($dependencyComposer['repositories'] ?? []) as $repository) {
                if (!is_array($repository) || 'path' !== ($repository['type'] ?? null)) {
                    continue;
                }

                $url = $repository['url'] ?? null;
                if (!is_string($url) || !str_starts_with(str_replace('\\', '/', $url), '../')) {
                    continue;
                }

                $transitivePath = realpath($packagePath.DIRECTORY_SEPARATOR.$url);
                if (false === $transitivePath) {
                    continue;
                }

                $transitiveComposerPath = $transitivePath.DIRECTORY_SEPARATOR.'composer.json';
                if (!is_file($transitiveComposerPath)) {
                    continue;
                }

                $transitiveComposer = json_decode((string) file_get_contents($transitiveComposerPath), true);
                $package = is_array($transitiveComposer) ? ($transitiveComposer['name'] ?? null) : null;
                if (!is_string($package) || !array_key_exists($package, $runtimeRequire)) {
                    continue;
                }

                $normalizedTransitivePath = $this->normalizePath($transitivePath);
                if (!isset($rootPaths[$normalizedTransitivePath])) {
                    $hits[$normalizedTransitivePath] = 'Root composer.json must expose local path repository for transitive first-party package '.$package.' ('.$url.').';
                }

                $queue[] = $transitivePath;
            }
        }

        return [] === $hits
            ? $this->result('passed', 'Root Composer manifest exposes the complete reachable local repository closure.')
            : $this->result('failed', 'Development Composer repository closure is incomplete.', array_values($hits));
    }

    private function normalizePath(string $path): string
    {
        return strtolower(str_replace('\\', '/', rtrim($path, '\\/')));
    }
}
