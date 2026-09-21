<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Target;

/**
 * Provides the target discovery implementation used by the Gating runtime and rule execution flow.
 */
final readonly class TargetDiscovery
{
    /** @var list<string> */
    private const array IGNORED_NAMES = [
        '.git',
        'vendor',
        'node_modules',
        'var',
        'cache',
        'report',
        'evidence',
    ];

    /**
     * @return list<array{nameEntity:string,path:string,relative_path:string,has_composer:bool,has_src:bool,has_gating:bool,profile_candidate:?string}>
     */
    public function discover(string $rootPath, int $maxDepth = 2): array
    {
        $root = realpath($rootPath);
        if (false === $root || !is_dir($root)) {
            throw new \RuntimeException(sprintf('Discovery root was not found: %s', $rootPath));
        }

        $targets = [];
        $seen = [];
        foreach ($this->candidateDirectories($root, $maxDepth) as $directory) {
            $real = realpath($directory);
            if (false === $real || isset($seen[$real])) {
                continue;
            }

            $hasComposer = is_file($real.DIRECTORY_SEPARATOR.'composer.json');
            $hasSrc = is_dir($real.DIRECTORY_SEPARATOR.'src');
            $hasGating = is_dir($real.DIRECTORY_SEPARATOR.'.gating');
            if (!$hasComposer && !$hasSrc && !$hasGating) {
                continue;
            }

            $seen[$real] = true;
            $targets[] = [
                'nameEntity' => basename($real),
                'path' => $real,
                'relative_path' => $this->relativePath($root, $real),
                'has_composer' => $hasComposer,
                'has_src' => $hasSrc,
                'has_gating' => $hasGating,
                'profile_candidate' => $this->profileCandidate($real),
            ];
        }

        usort($targets, static fn (array $left, array $right): int => strcmp($left['relative_path'], $right['relative_path']));

        return $targets;
    }

    /** @return iterable<string> */
    private function candidateDirectories(string $root, int $maxDepth): iterable
    {
        yield $root;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isDir()) {
                continue;
            }

            $path = $file->getPathname();
            if ($this->isIgnored($path)) {
                continue;
            }

            $relative = $this->relativePath($root, $path);
            $depth = '.' === $relative ? 0 : substr_count($relative, '/') + 1;
            if ($depth > $maxDepth) {
                continue;
            }

            yield $path;
        }
    }

    /**
     * Determines whether ignored satisfies the current Gating condition.
     */
    private function isIgnored(string $path): bool
    {
        $segments = preg_split('#[\\\\/]#', $path) ?: [];

        return array_any($segments, fn ($segment) => in_array($segment, self::IGNORED_NAMES, true));
    }

    /**
     * Executes the relative path responsibility defined by this Gating component.
     */
    private function relativePath(string $root, string $path): string
    {
        $relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');

        return '' === $relative ? '.' : $relative;
    }

    /**
     * Executes the profile candidate responsibility defined by this Gating component.
     */
    private function profileCandidate(string $targetPath): ?string
    {
        $localProfile = $targetPath.DIRECTORY_SEPARATOR.'.gating'.DIRECTORY_SEPARATOR.'profile'.DIRECTORY_SEPARATOR.'component'.DIRECTORY_SEPARATOR.strtolower(basename($targetPath)).'.yaml';
        if (is_file($localProfile)) {
            return $localProfile;
        }

        $template = $targetPath.DIRECTORY_SEPARATOR.'.gating'.DIRECTORY_SEPARATOR.'profile'.DIRECTORY_SEPARATOR.'component'.DIRECTORY_SEPARATOR.'_template.yaml';
        if (is_file($template)) {
            return $template;
        }

        return null;
    }
}
