<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Profile;

/**
 * Provides the profile loader implementation used by the Gating runtime and rule execution flow.
 */
final readonly class ProfileLoader
{
    /** @return array<string, mixed> */
    public function load(?string $profilePath): array
    {
        if (null === $profilePath || '' === $profilePath) {
            return [];
        }

        $realPath = realpath($profilePath);
        if (false === $realPath || !is_file($realPath)) {
            throw new \RuntimeException(sprintf('Profile file was not found: %s', $profilePath));
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $raw = file_get_contents($realPath);
        if (false === $raw) {
            throw new \RuntimeException(sprintf('Profile file could not be read: %s', $profilePath));
        }

        if ('json' === $extension) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException(sprintf('Profile JSON is invalid: %s', $profilePath));
            }

            return $decoded;
        }

        return $this->parseSimpleYaml($raw);
    }

    /**
     * Minimal YAML reader for flat and two-level profile files.
     * It intentionally avoids becoming a general YAML implementation.
     *
     * @return array<string, mixed>
     */
    private function parseSimpleYaml(string $raw): array
    {
        $result = [];
        $path = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            if ('' === trim($line) || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match('/^(\s*)-\s*(.+)$/', $line, $listMatches)) {
                $indent = strlen($listMatches[1]);
                $level = intdiv($indent, 2);
                $listPath = array_slice($path, 0, $level);
                $value = trim(trim($listMatches[2]), "'\"");
                $this->appendNestedValue($result, $listPath, $value);
                continue;
            }

            if (!preg_match('/^(\s*)([^:#]+):\s*(.*)$/', $line, $matches)) {
                continue;
            }

            $indent = strlen($matches[1]);
            $key = trim($matches[2]);
            $value = trim($matches[3]);
            $level = intdiv($indent, 2);
            $path = array_slice($path, 0, $level);

            if ('' === $value) {
                $path[$level] = $key;
                $this->setNestedValue($result, array_merge($path), []);
                continue;
            }

            $value = trim($value, "'\"");
            $this->setNestedValue($result, array_merge($path, [$key]), $value);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string>         $path
     */
    private function appendNestedValue(array &$target, array $path, string $value): void
    {
        $cursor = &$target;
        foreach ($path as $key) {
            if (!isset($cursor[$key]) || !is_array($cursor[$key])) {
                $cursor[$key] = [];
            }
            $cursor = &$cursor[$key];
        }

        $cursor[] = $value;
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string>         $path
     */
    private function setNestedValue(array &$target, array $path, mixed $value): void
    {
        $cursor = &$target;
        foreach ($path as $index => $key) {
            if ($index === array_key_last($path)) {
                $cursor[$key] = $value;

                return;
            }
            if (!isset($cursor[$key]) || !is_array($cursor[$key])) {
                $cursor[$key] = [];
            }
            $cursor = &$cursor[$key];
        }
    }
}
