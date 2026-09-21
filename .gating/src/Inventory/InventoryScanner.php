<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Inventory;

/**
 * Provides the inventory scanner implementation used by the Gating runtime and rule execution flow.
 */
final readonly class InventoryScanner
{
    private const array DEFAULT_EXCLUDED_ROOTS = [
        '.git',
        '.idea',
        'node_modules',
        'public/bundles',
        'public/build',
        'var',
        'vendor',
    ];

    /**
     * @param list<string> $excludedPaths
     *
     * @return array<string, int>
     */
    public function scan(string $targetPath, array $excludedPaths = []): array
    {
        $counters = [
            'php_files' => 0,
            'controller_files' => 0,
            'entity_files' => 0,
            'service_files' => 0,
            'service_interface_files' => 0,
            'repository_files' => 0,
            'command_files' => 0,
            'route_config_files' => 0,
            'composer_files' => 0,
            'tool_files' => 0,
            'documentation_files' => 0,
        ];

        foreach ($this->files($targetPath, array_values(array_unique(array_merge(self::DEFAULT_EXCLUDED_ROOTS, $excludedPaths)))) as $path) {
            $relative = str_replace('\\', '/', substr($path, strlen(rtrim($targetPath, '/\\')) + 1));
            $basename = basename($path);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if ('php' === $extension) {
                ++$counters['php_files'];
            }
            if (str_contains($relative, '/Controller/') || str_starts_with($relative, 'src/Controller/') || str_ends_with($basename, 'Controller.php')) {
                ++$counters['controller_files'];
            }
            if (str_contains('/'.$relative, '/Entity/')) {
                ++$counters['entity_files'];
            }
            if (str_contains('/'.$relative, '/Service/')) {
                ++$counters['service_files'];
            }
            if (str_contains('/'.$relative, '/ServiceInterface/')) {
                ++$counters['service_interface_files'];
            }
            if (str_contains('/'.$relative, '/Repository/')) {
                ++$counters['repository_files'];
            }
            if (str_contains('/'.$relative, '/Command/') || str_ends_with($basename, 'Command.php')) {
                ++$counters['command_files'];
            }
            if (1 === preg_match('#(^|/)config/(routes|routes\.yaml|routes/)#', $relative) || str_contains($relative, '/routes/')) {
                ++$counters['route_config_files'];
            }
            if ('composer.json' === $basename) {
                ++$counters['composer_files'];
            }
            if (1 === preg_match('#(^|/)(tool|tools|linting|quality)/#', $relative)) {
                ++$counters['tool_files'];
            }
            if (in_array($extension, ['md', 'adoc', 'rst'], true)) {
                ++$counters['documentation_files'];
            }
        }

        return $counters;
    }

    /**
     * @param list<string> $excludedPaths
     *
     * @return iterable<string>
     */
    private function files(string $targetPath, array $excludedPaths): iterable
    {
        $root = rtrim(str_replace('\\', '/', realpath($targetPath) ?: $targetPath), '/');
        $directory = new \RecursiveDirectoryIterator($targetPath, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator(
            $directory,
            function (\SplFileInfo $current) use ($root, $excludedPaths): bool {
                $pathname = str_replace('\\', '/', $current->getPathname());
                $relative = ltrim(substr($pathname, strlen($root)), '/');

                foreach ($excludedPaths as $pattern) {
                    $normalized = trim(str_replace('\\', '/', $pattern), '/');
                    if ('' === $normalized) {
                        continue;
                    }
                    if ($relative === $normalized || str_starts_with($relative, $normalized.'/')) {
                        return false;
                    }
                    if (function_exists('fnmatch') && fnmatch($normalized, $relative, FNM_PATHNAME)) {
                        return false;
                    }
                }

                return true;
            },
        );

        $iterator = new \RecursiveIteratorIterator($filter);
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                yield $file->getPathname();
            }
        }
    }
}
