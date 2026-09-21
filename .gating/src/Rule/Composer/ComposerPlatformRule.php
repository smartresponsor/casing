<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Composer;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the composer platform rule implementation used by the Gating runtime and rule execution flow.
 */
final class ComposerPlatformRule implements RuleInterface
{
    /** @var list<string> */
    private const array DEFAULT_SYMFONY_FRAMEWORK_PACKAGES = [
        'symfony/asset',
        'symfony/browser-kit',
        'symfony/cache',
        'symfony/config',
        'symfony/console',
        'symfony/css-selector',
        'symfony/dependency-injection',
        'symfony/doctrine-bridge',
        'symfony/dotenv',
        'symfony/event-dispatcher',
        'symfony/expression-language',
        'symfony/filesystem',
        'symfony/finder',
        'symfony/form',
        'symfony/framework-bundle',
        'symfony/http-client',
        'symfony/http-foundation',
        'symfony/http-kernel',
        'symfony/intl',
        'symfony/lock',
        'symfony/mailer',
        'symfony/messenger',
        'symfony/mime',
        'symfony/notifier',
        'symfony/options-resolver',
        'symfony/password-hasher',
        'symfony/process',
        'symfony/property-access',
        'symfony/property-info',
        'symfony/rate-limiter',
        'symfony/routing',
        'symfony/runtime',
        'symfony/security-bundle',
        'symfony/security-core',
        'symfony/security-csrf',
        'symfony/serializer',
        'symfony/string',
        'symfony/translation',
        'symfony/twig-bridge',
        'symfony/twig-bundle',
        'symfony/uid',
        'symfony/validator',
        'symfony/var-dumper',
        'symfony/var-exporter',
        'symfony/web-link',
        'symfony/workflow',
        'symfony/yaml',
        'symfony/phpunit-bridge',
    ];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'composer.platform_constraint';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $composerPath = $context->targetPath.DIRECTORY_SEPARATOR.'composer.json';
        if (!is_file($composerPath)) {
            return new RuleResult($this->id(), 'skipped', 'Target has no composer.json.');
        }

        $raw = file_get_contents($composerPath);
        if (false === $raw) {
            return new RuleResult($this->id(), 'failed', 'composer.json could not be read.');
        }

        $composer = json_decode($raw, true);
        if (!is_array($composer)) {
            return new RuleResult($this->id(), 'failed', 'composer.json is not valid JSON.');
        }

        $platform = $context->profile['ecosystem']['platform'] ?? [];
        $expectedPhp = $platform['php'] ?? $context->profile['ecosystem']['php_constraint'] ?? $context->profile['php_constraint'] ?? '^8.4';
        $expectedSymfony = $platform['symfony']['constraint'] ?? $context->profile['ecosystem']['symfony_constraint'] ?? $context->profile['symfony_constraint'] ?? '^8.0';
        $frameworkPackages = $platform['symfony']['packages'] ?? self::DEFAULT_SYMFONY_FRAMEWORK_PACKAGES;
        $frameworkPackages = is_array($frameworkPackages) ? array_fill_keys(array_values(array_filter($frameworkPackages, is_string(...))), true) : array_fill_keys(self::DEFAULT_SYMFONY_FRAMEWORK_PACKAGES, true);

        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $requireDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];
        $allRequirements = array_merge($require, $requireDev);
        $hits = [];

        $phpConstraint = $require['php'] ?? null;
        if (!is_string($phpConstraint) || !$this->constraintsEquivalent($phpConstraint, (string) $expectedPhp)) {
            $hits[] = sprintf('composer.json require.php is %s, expected %s.', is_string($phpConstraint) ? $phpConstraint : '<missing>', $expectedPhp);
        }

        foreach ($allRequirements as $package => $constraint) {
            if (!is_string($package) || !isset($frameworkPackages[$package])) {
                continue;
            }
            if (!is_string($constraint)) {
                $hits[] = sprintf('%s has non-string constraint.', $package);
                continue;
            }
            if (!$this->constraintsEquivalent($constraint, (string) $expectedSymfony)) {
                $hits[] = sprintf('%s constraint is %s, expected a constraint equivalent to %s.', $package, $constraint, $expectedSymfony);
            }
        }

        if ([] !== $hits) {
            return new RuleResult($this->id(), 'failed', 'Composer platform constraints do not match the Gating profile.', $hits);
        }

        return new RuleResult($this->id(), 'passed', 'Composer platform constraints match the Gating profile.');
    }

    /**
     * Executes the constraints equivalent responsibility defined by this Gating component.
     */
    private function constraintsEquivalent(string $actual, string $expected): bool
    {
        $actual = preg_replace('/\s+/', '', trim($actual)) ?? trim($actual);
        $expected = preg_replace('/\s+/', '', trim($expected)) ?? trim($expected);
        if ($actual === $expected) {
            return true;
        }

        $actualFloor = $this->constraintFloor($actual);
        $expectedFloor = $this->constraintFloor($expected);
        if (null === $actualFloor || null === $expectedFloor) {
            return false;
        }

        [$actualMajor, $actualMinor] = array_map(intval(...), explode('.', $actualFloor));
        [$expectedMajor, $expectedMinor] = array_map(intval(...), explode('.', $expectedFloor));

        return $actualMajor === $expectedMajor && $actualMinor >= $expectedMinor;
    }

    /**
     * Executes the constraint floor responsibility defined by this Gating component.
     */
    private function constraintFloor(string $constraint): ?string
    {
        if (1 !== preg_match('/^(?:\^|~|>=)?(\d+)\.(\d+)/', $constraint, $matches)) {
            return null;
        }

        return $matches[1].'.'.$matches[2];
    }
}
