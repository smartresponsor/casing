<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Layer;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the typed layer rule implementation used by the Gating runtime and rule execution flow.
 */
final class TypedLayerRule implements RuleInterface
{
    /** @var array<string, list<string>> */
    private const array DEFAULT_SUFFIX_TO_LAYERS = [
        'EventSubscriber' => ['EventSubscriber'],
        'Controller' => ['Controller'],
        'Authenticator' => ['Authenticator'],
        'Middleware' => ['Middleware'],
        'Repository' => ['Repository'],
        'Subscriber' => ['Subscriber', 'EventSubscriber'],
        'Listener' => ['Listener'],
        'Command' => ['Command'],
        'Entity' => ['Entity'],
        'Voter' => ['Voter'],
        'FormType' => ['Form'],
        'Event' => ['Event'],
    ];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'layer.typed_class_location';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $src = $context->targetPath.DIRECTORY_SEPARATOR.'src';
        if (!is_dir($src)) {
            return new RuleResult($this->id(), 'skipped', 'Target has no src/ directory.');
        }

        $mapping = $this->mapping($context->profile);
        $hits = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $basename = $file->getBasename('.php');
            $relative = $this->relativePath($context->targetPath, $file->getPathname());
            foreach ($mapping as $suffix => $layers) {
                if (!str_ends_with($basename, $suffix)) {
                    continue;
                }

                $matchesLayer = false;
                foreach ($layers as $layer) {
                    if (str_starts_with($relative, 'src/'.trim($layer, '/').'/')) {
                        $matchesLayer = true;
                        break;
                    }
                }

                if (!$matchesLayer) {
                    $hits[] = sprintf('%s expected under one of: %s', $relative, implode(', ', array_map(static fn (string $layer): string => 'src/'.trim($layer, '/').'/', $layers)));
                }
                break;
            }
        }

        if ([] !== $hits) {
            return new RuleResult($this->id(), 'failed', 'Class suffix and typed layer directory do not match.', $hits);
        }

        return new RuleResult($this->id(), 'passed', 'Class suffixes match their typed layer directories.');
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return array<string, list<string>>
     */
    private function mapping(array $profile): array
    {
        $declared = $profile['component']['typed_layer_map'] ?? $profile['typed_layer_map'] ?? null;
        if (!is_array($declared)) {
            return self::DEFAULT_SUFFIX_TO_LAYERS;
        }

        $mapping = [];
        foreach ($declared as $suffix => $layers) {
            if (!is_string($suffix) || '' === $suffix) {
                continue;
            }
            if (is_string($layers) && '' !== $layers) {
                $mapping[$suffix] = [$layers];
                continue;
            }
            if (is_array($layers)) {
                $allowed = array_values(array_filter($layers, static fn (mixed $layer): bool => is_string($layer) && '' !== $layer));
                if ([] !== $allowed) {
                    $mapping[$suffix] = $allowed;
                }
            }
        }

        if ([] === $mapping) {
            return self::DEFAULT_SUFFIX_TO_LAYERS;
        }

        uksort($mapping, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return $mapping;
    }

    /**
     * Executes the relative path responsibility defined by this Gating component.
     */
    private function relativePath(string $root, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    }
}
