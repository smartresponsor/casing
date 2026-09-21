<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Route;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;
use Gating\Gate\Policy\Route\RoutePolicy;

/**
 * Provides the route owner root rule implementation used by the Gating runtime and rule execution flow.
 */
final class RouteOwnerRootRule implements RuleInterface
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'route.owner_root';
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

        $allowedOwners = RoutePolicy::allowedOwners($context->profile);
        $hits = [];
        $checked = 0;
        foreach ($routes as $route) {
            if (RoutePolicy::excludes($route, $context->profile)) {
                continue;
            }
            ++$checked;
            $issue = $this->validateOwner($route['path'], $allowedOwners);
            if (null !== $issue) {
                $hits[] = sprintf('%s [%s in %s]', $issue, $route['name'], $route['source']);
            }
        }

        if (0 === $checked) {
            return new RuleResult($this->id(), 'skipped', 'All discovered Symfony routes were excluded by profile.');
        }
        if ([] !== $hits) {
            return new RuleResult($this->id(), 'failed', 'Route paths must begin with an allowed stable owner root.', $hits);
        }

        return new RuleResult($this->id(), 'passed', sprintf('Route paths begin with allowed owner roots (%d route(s)).', $checked));
    }

    /** @param list<string> $allowedOwners */
    private function validateOwner(string $route, array $allowedOwners): ?string
    {
        $route = trim($route);
        if ('' === $route || '/' !== $route[0]) {
            return sprintf('%s must be an absolute route path.', '' === $route ? '<empty route>' : $route);
        }

        $segments = array_values(array_filter(explode('/', trim($route, '/')), static fn (string $segment): bool => '' !== $segment));
        if ([] === $segments) {
            return null;
        }
        if ('api' === $segments[0]) {
            array_shift($segments);
        }
        if ([] === $segments) {
            return sprintf('%s has channel prefix but no owner root.', $route);
        }

        $owner = $segments[0];
        if ($this->isDynamic($owner)) {
            return sprintf('%s starts with dynamic segment %s; the first segment after optional /api must be a stable owner root.', $route, $owner);
        }
        if ([] !== $allowedOwners && !in_array($owner, $allowedOwners, true)) {
            return sprintf('%s owner root is %s, expected one of: %s.', $route, $owner, implode(', ', $allowedOwners));
        }

        return null;
    }

    /**
     * Determines whether dynamic satisfies the current Gating condition.
     */
    private function isDynamic(string $segment): bool
    {
        return str_starts_with($segment, '{') && str_ends_with($segment, '}');
    }
}
