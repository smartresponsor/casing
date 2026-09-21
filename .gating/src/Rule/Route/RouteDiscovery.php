<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Route;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides the route discovery implementation used by the Gating runtime and rule execution flow.
 */
final class RouteDiscovery
{
    /** @return list<array{name:string,path:string,source:string}> */
    public function discover(string $root): array
    {
        return $this->uniqueRoutes(array_merge($this->phpAttributeRoutes($root), $this->yamlRoutes($root)));
    }

    /** @return list<array{name:string,path:string,source:string}> */
    private function phpAttributeRoutes(string $root): array
    {
        $src = $root.DIRECTORY_SEPARATOR.'src';
        if (!is_dir($src)) {
            return [];
        }

        $routes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (false === $contents || !str_contains($contents, 'Route')) {
                continue;
            }

            $relative = $this->relativePath($root, $file->getPathname());
            foreach ([
                '/#\[Route\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/m',
                '/#\[Route\s*\([^\)]*path\s*:\s*[\'\"]([^\'\"]+)[\'\"]/m',
            ] as $pattern) {
                if (false === preg_match_all($pattern, $contents, $matches)) {
                    continue;
                }
                foreach ($matches[1] as $index => $path) {
                    $routes[] = ['name' => 'attribute-'.($index + 1), 'path' => (string) $path, 'source' => $relative];
                }
            }
        }

        return $routes;
    }

    /** @return list<array{name:string,path:string,source:string}> */
    private function yamlRoutes(string $root): array
    {
        $config = $root.DIRECTORY_SEPARATOR.'config';
        if (!is_dir($config)) {
            return [];
        }

        $routes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($config, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !in_array(strtolower($file->getExtension()), ['yaml', 'yml'], true)) {
                continue;
            }
            if (!str_contains(str_replace('\\', '/', $file->getPathname()), '/routes')) {
                continue;
            }

            try {
                $document = Yaml::parseFile($file->getPathname());
            } catch (ParseException) {
                continue;
            }
            if (!is_array($document)) {
                continue;
            }

            $relative = $this->relativePath($root, $file->getPathname());
            foreach ($document as $name => $definition) {
                if (!is_string($name) || !is_array($definition)) {
                    continue;
                }
                $path = $definition['path'] ?? null;
                if (!is_string($path) || '' === $path) {
                    continue;
                }
                $routes[] = ['name' => $name, 'path' => $path, 'source' => $relative];
            }
        }

        return $routes;
    }

    /**
     * @param list<array{name:string,path:string,source:string}> $routes
     *
     * @return list<array{name:string,path:string,source:string}>
     */
    private function uniqueRoutes(array $routes): array
    {
        $unique = [];
        foreach ($routes as $route) {
            $unique[$route['source'].'|'.$route['name'].'|'.$route['path']] = $route;
        }

        return array_values($unique);
    }

    /**
     * Executes the relative path responsibility defined by this Gating component.
     */
    private function relativePath(string $root, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    }
}
