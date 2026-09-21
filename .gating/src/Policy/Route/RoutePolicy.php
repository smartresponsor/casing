<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Policy\Route;

/**
 * Provides the route policy implementation used by the Gating runtime and rule execution flow.
 */
final class RoutePolicy
{
    /**
     * @param array<string, mixed> $profile
     *
     * @return list<string>
     */
    public static function allowedOwners(array $profile): array
    {
        $declared = $profile['component']['route_owner_roots']
            ?? $profile['route_owner_roots']
            ?? $profile['component']['route_owner_root']
            ?? null;

        if (is_string($declared) && '' !== $declared) {
            return [$declared];
        }
        if (is_array($declared)) {
            return array_values(array_filter($declared, static fn (mixed $owner): bool => is_string($owner) && '' !== $owner));
        }

        $businessPrefix = $profile['component']['business_prefix'] ?? $profile['business_prefix'] ?? null;

        return is_string($businessPrefix) && '' !== $businessPrefix
            ? [strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $businessPrefix) ?: $businessPrefix)]
            : [];
    }

    /**
     * @param array{name:string,path:string,source:string} $route
     * @param array<string, mixed>                         $profile
     */
    public static function excludes(array $route, array $profile): bool
    {
        $component = is_array($profile['component'] ?? null) ? $profile['component'] : [];

        return self::matches((string) $route['path'], self::strings($component['route_excluded_paths'] ?? $profile['route_excluded_paths'] ?? []))
            || self::matches((string) $route['name'], self::strings($component['route_excluded_names'] ?? $profile['route_excluded_names'] ?? []))
            || self::matches((string) $route['source'], self::strings($component['route_excluded_sources'] ?? $profile['route_excluded_sources'] ?? []));
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && '' !== $item))
            : [];
    }

    /** @param list<string> $patterns */
    private static function matches(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($value === $pattern) {
                return true;
            }
            if (function_exists('fnmatch') && fnmatch($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
