<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Route;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;
use Gating\Gate\Policy\Route\RoutePolicy;

/**
 * Provides the route path segment separation rule implementation used by the Gating runtime and rule execution flow.
 */
final class RoutePathSegmentSeparationRule implements RuleInterface
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'route.path_segment_separation';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $routes = new RouteDiscovery()->discover($context->targetPath);
        if ([] === $routes) {
            return new RuleResult($this->id(), 'skipped', 'No Symfony route paths were discovered.');
        }

        $hits = [];
        $checked = 0;
        foreach ($routes as $route) {
            if (RoutePolicy::excludes($route, $context->profile)) {
                continue;
            }
            ++$checked;
            foreach ($this->validatePath($route['path']) as $issue) {
                $hits[] = sprintf('%s [%s in %s]', $issue, $route['name'], $route['source']);
            }
        }

        if (0 === $checked) {
            return new RuleResult($this->id(), 'skipped', 'All discovered Symfony routes were excluded by profile.');
        }
        if ([] !== $hits) {
            return new RuleResult(
                $this->id(),
                'failed',
                'Each route concept must use its own slash-separated path segment.',
                $hits,
            );
        }

        return new RuleResult($this->id(), 'passed', sprintf('Route concepts use separate path segments (%d route(s)).', $checked));
    }

    /** @return list<string> */
    private function validatePath(string $route): array
    {
        $route = trim($route);
        if ('' === $route || '/' !== $route[0]) {
            return [sprintf('%s must be an absolute route path.', '' === $route ? '<empty route>' : $route)];
        }

        $segments = array_values(array_filter(explode('/', trim($route, '/')), static fn (string $segment): bool => '' !== $segment));
        $issues = [];
        foreach ($segments as $index => $segment) {
            if ($this->isDynamic($segment)) {
                $nameEntity = trim($segment, '{}');
                if (!in_array($nameEntity, ['id', 'slug', 'token'], true)) {
                    $issues[] = sprintf('%s uses unsupported dynamic segment %s; only {id}, {slug}, and {token} are canonical.', $route, $segment);
                }
                if ($index !== count($segments) - 1) {
                    $issues[] = sprintf('%s uses %s before the final path segment; {id}/{slug} may appear only at the end.', $route, $segment);
                }
                continue;
            }

            if (str_contains($segment, '-') || str_contains($segment, '_')) {
                $issues[] = sprintf(
                    '%s joins multiple route concepts in path segment %s; use separate slash-delimited segments.',
                    $route,
                    $segment,
                );
            }
        }

        return $issues;
    }

    /**
     * Determines whether dynamic satisfies the current Gating condition.
     */
    private function isDynamic(string $segment): bool
    {
        return str_starts_with($segment, '{') && str_ends_with($segment, '}');
    }
}
