<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Release;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the release evidence rule implementation used by the Gating runtime and rule execution flow.
 */
final class ReleaseEvidenceRule implements RuleInterface
{
    /** @var list<string> */
    private const array DEFAULT_REQUIRED_FILES = [
        'MANIFEST.json',
        'composer.json',
    ];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'release.evidence_manifest';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $release = $context->profile['component']['release'] ?? $context->profile['release'] ?? [];
        if (!is_array($release)) {
            $release = [];
        }

        $required = $release['evidence_required'] ?? $release['required'] ?? false;
        if (!$this->isTruthy($required)) {
            return new RuleResult($this->id(), 'skipped', 'Release evidence is not required by profile for this target.');
        }

        $requiredFiles = $release['required_files'] ?? self::DEFAULT_REQUIRED_FILES;
        if (!is_array($requiredFiles) || [] === $requiredFiles) {
            $requiredFiles = self::DEFAULT_REQUIRED_FILES;
        }

        $missing = [];
        foreach ($requiredFiles as $requiredFile) {
            if (!is_string($requiredFile) || '' === trim($requiredFile)) {
                continue;
            }
            $path = $context->targetPath.'/'.ltrim(str_replace('\\', '/', $requiredFile), '/');
            if (!is_file($path)) {
                $missing[] = sprintf('Missing release evidence file: %s', $requiredFile);
            }
        }

        $manifestPath = $context->targetPath.'/MANIFEST.json';
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                $missing[] = 'MANIFEST.json exists but is not valid JSON.';
            }
        }

        if ([] !== $missing) {
            return new RuleResult($this->id(), 'failed', 'Required release evidence is incomplete.', $missing);
        }

        return new RuleResult($this->id(), 'passed', sprintf('Release evidence is present (%d required file(s)).', count($requiredFiles)));
    }

    /**
     * Determines whether truthy satisfies the current Gating condition.
     */
    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'required', 'enabled'], true);
        }

        return false;
    }
}
