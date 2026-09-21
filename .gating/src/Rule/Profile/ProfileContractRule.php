<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Profile;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the profile contract rule implementation used by the Gating runtime and rule execution flow.
 */
final class ProfileContractRule implements RuleInterface
{
    /** @var list<string> */
    private const array REQUIRED_COMPONENT_KEYS = [
        'nameEntity',
        'namespace',
        'business_prefix',
        'database_prefix',
    ];

    /** @var list<string> */
    private const array KNOWN_RULE_IDS = [
        'profile.contract_validity',
        'structure.forbidden_architecture',
        'namespace.profile_match',
        'layer.typed_class_location',
        'mirror.service_interface',
        'composer.platform_constraint',
        'database.table_prefix',
        'route.owner_token',
        'route.owner_root',
        'route.path_segment_separation',
        'mutation.safety_firewall',
        'security.secret_leak',
        'release.evidence_manifest',
        'documentation.docblock_preservation',
        'inventory.component_surface',
    ];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'profile.contract_validity';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        if ([] === $context->profile) {
            return new RuleResult($this->id(), 'skipped', 'No profile was provided.');
        }

        $component = $context->profile['component'] ?? null;
        if (!is_array($component)) {
            return new RuleResult($this->id(), 'failed', 'Profile must contain a component section.');
        }

        $errors = [];
        foreach (self::REQUIRED_COMPONENT_KEYS as $key) {
            $value = $component[$key] ?? null;
            if (!is_string($value) || '' === trim($value)) {
                $errors[] = sprintf('component.%s must be a non-empty string.', $key);
            }
        }

        $databasePrefix = $component['database_prefix'] ?? null;
        if (is_string($databasePrefix) && '' !== $databasePrefix && !str_ends_with($databasePrefix, '_')) {
            $errors[] = 'component.database_prefix should end with an underscore.';
        }

        $namespace = $component['namespace'] ?? null;
        if (is_string($namespace) && '' !== $namespace && str_contains($namespace, '/')) {
            $errors[] = 'component.namespace must use PHP namespace separators, not path separators.';
        }

        $mutationMode = $component['mutation_mode'] ?? null;
        if (null !== $mutationMode && !in_array($mutationMode, ['forbidden', 'plan', 'explicit'], true)) {
            $errors[] = 'component.mutation_mode must be one of: forbidden, plan, explicit.';
        }

        $routeOwnerRoots = $component['route_owner_roots'] ?? [];
        if ([] !== $routeOwnerRoots && !is_array($routeOwnerRoots)) {
            $errors[] = 'component.route_owner_roots must be a list.';
        }
        if (is_array($routeOwnerRoots)) {
            foreach ($routeOwnerRoots as $root) {
                if (!is_string($root) || '' === trim($root)) {
                    $errors[] = 'component.route_owner_roots must contain only non-empty strings.';
                    continue;
                }
                if (str_contains($root, '/') || str_contains($root, '-') || str_contains($root, '{')) {
                    $errors[] = sprintf('route owner root "%s" must be a plain singular token.', $root);
                }
            }
        }

        foreach (['route_excluded_paths', 'route_excluded_names', 'route_excluded_sources', 'inventory_excluded_paths'] as $listKey) {
            $values = $component[$listKey] ?? [];
            if ([] !== $values && !is_array($values)) {
                $errors[] = sprintf('component.%s must be a list.', $listKey);
                continue;
            }
            foreach ($values as $value) {
                if (!is_string($value) || '' === trim($value)) {
                    $errors[] = sprintf('component.%s must contain only non-empty strings.', $listKey);
                }
            }
        }

        $enabledRules = $component['enabled_rules'] ?? [];
        if ([] !== $enabledRules && !is_array($enabledRules)) {
            $errors[] = 'component.enabled_rules must be a list when present.';
        }
        if (is_array($enabledRules)) {
            $known = array_fill_keys(self::KNOWN_RULE_IDS, true);
            foreach ($enabledRules as $ruleId) {
                if (!is_string($ruleId) || !isset($known[$ruleId])) {
                    $errors[] = sprintf('component.enabled_rules contains unknown rule id: %s', is_scalar($ruleId) ? (string) $ruleId : gettype($ruleId));
                }
            }
        }

        $suppressions = $context->profile['suppressions'] ?? [];
        if ([] !== $suppressions && !is_array($suppressions)) {
            $errors[] = 'suppressions must be a map of rule id to evidence substring list.';
        }
        if (is_array($suppressions)) {
            $known = array_fill_keys(self::KNOWN_RULE_IDS, true);
            foreach ($suppressions as $ruleId => $patterns) {
                if (!is_string($ruleId) || !isset($known[$ruleId])) {
                    $errors[] = sprintf('suppressions contains unknown rule id: %s', (string) $ruleId);
                    continue;
                }
                if (!is_array($patterns)) {
                    $errors[] = sprintf('suppressions.%s must be a list of evidence substrings.', $ruleId);
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (!is_string($pattern) || '' === trim($pattern)) {
                        $errors[] = sprintf('suppressions.%s must contain only non-empty strings.', $ruleId);
                    }
                }
            }
        }

        $release = $component['release'] ?? [];
        if ([] !== $release && !is_array($release)) {
            $errors[] = 'component.release must be a map when present.';
        }
        if (is_array($release) && array_key_exists('evidence_required', $release)) {
            $value = $release['evidence_required'];
            if (!is_bool($value) && !in_array($value, ['true', 'false', '1', '0'], true)) {
                $errors[] = 'component.release.evidence_required must be boolean-like.';
            }
        }

        if ([] !== $errors) {
            return new RuleResult($this->id(), 'failed', 'Component profile contract errors were found.', $errors);
        }

        return new RuleResult($this->id(), 'passed', 'Component profile matches the Gating profile contract.');
    }
}
