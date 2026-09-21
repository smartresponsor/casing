<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Route;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/** @deprecated Use RouteOwnerRootRule and RoutePathSegmentSeparationRule. */
final class RouteOwnershipRule implements RuleInterface
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'route.owner_token';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $owner = new RouteOwnerRootRule()->check($context);
        $segments = new RoutePathSegmentSeparationRule()->check($context);

        $evidence = array_merge($owner->evidence, $segments->evidence);
        if ('failed' === $owner->status || 'failed' === $segments->status) {
            return new RuleResult($this->id(), 'failed', 'Legacy combined route policy failed. Use route.owner_root and route.path_segment_separation.', $evidence);
        }
        if ('skipped' === $owner->status && 'skipped' === $segments->status) {
            return new RuleResult($this->id(), 'skipped', 'No applicable Symfony routes were discovered.');
        }

        return new RuleResult($this->id(), 'passed', 'Legacy combined route policy passed.');
    }
}
