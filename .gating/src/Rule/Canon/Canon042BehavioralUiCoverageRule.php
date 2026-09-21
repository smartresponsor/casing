<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Evaluates Canon042 functional/behavioral/UI coverage evidence.
 */
final class Canon042BehavioralUiCoverageRule extends AbstractCanonRule
{
    private const string SCHEMA = 'behavioral-ui-coverage-v2';

    private const array THRESHOLDS = [
        'functional' => 80.0,
        'behavioral' => 80.0,
        'ui' => 70.0,
        'critical' => 100.0,
    ];

    private const array HIGH_DEBT = [
        'functional' => 50.0,
        'behavioral' => 50.0,
        'ui' => 40.0,
        'critical' => 100.0,
    ];

    public function id(): string
    {
        return 'canon.042.behavioral_ui_coverage';
    }

    public function check(RuleContext $context): RuleResult
    {
        if (!$this->isSymfonyApplication($context)) {
            return $this->result('skipped', 'No standalone Symfony application runtime was detected.');
        }

        $path = $context->targetPath.'/var/coverage/behavioral-ui.json';
        if (!is_file($path)) {
            return $this->result(
                'warning',
                'Behavioral/UI coverage evidence is missing.',
                ['Generate var/coverage/behavioral-ui.json from the repository test-surface coverage workflow.'],
                'warning',
            );
        }

        $evidence = json_decode((string) file_get_contents($path), true);
        if (!is_array($evidence)) {
            return $this->invalid('Behavioral/UI coverage evidence is not valid JSON.');
        }
        if (self::SCHEMA !== ($evidence['schema'] ?? null)) {
            return $this->invalid('Behavioral/UI coverage evidence uses an unverifiable legacy schema; regenerate explicit surface inventories.');
        }

        $producer = $evidence['producer'] ?? null;
        if (!is_array($producer) || 'repository_script' !== ($producer['kind'] ?? null) || !is_string($producer['script'] ?? null) || '' === trim($producer['script'])) {
            return $this->invalid('Behavioral/UI coverage evidence must identify a repository_script producer.');
        }
        if (!$this->isDeclaredProducerScript($context, $producer['script'])) {
            return $this->invalid('Behavioral/UI coverage producer script is not declared by the repository package manifests.');
        }

        $generatedAt = $evidence['generatedAt'] ?? null;
        if (!is_string($generatedAt)) {
            return $this->invalid('Behavioral/UI coverage evidence must include generatedAt.');
        }
        try {
            $generatedAtTime = new \DateTimeImmutable($generatedAt)->getTimestamp();
        } catch (\Exception) {
            return $this->invalid('Behavioral/UI coverage generatedAt is invalid.');
        }
        if ($generatedAtTime > time() + 300) {
            return $this->invalid('Behavioral/UI coverage generatedAt cannot be materially in the future.');
        }

        $dimensions = $evidence['dimensions'] ?? null;
        if (!is_array($dimensions)) {
            return $this->invalid('Behavioral/UI coverage evidence must include explicit dimension inventories.');
        }

        $percentages = [];
        $details = [];
        foreach (self::THRESHOLDS as $dimension => $threshold) {
            $metric = $dimensions[$dimension] ?? null;
            if (!is_array($metric) || !$this->isStringList($metric['eligible'] ?? null) || !$this->isStringList($metric['covered'] ?? null)) {
                return $this->invalid('Missing explicit eligible/covered inventories for '.$dimension.'.');
            }

            $eligible = array_values(array_unique($metric['eligible']));
            $coveredInventory = array_values(array_unique($metric['covered']));
            if (count($eligible) !== count($metric['eligible']) || count($coveredInventory) !== count($metric['covered'])) {
                return $this->invalid('Behavioral/UI inventories must not contain duplicate identifiers for '.$dimension.'.');
            }
            foreach ($coveredInventory as $identifier) {
                if (!in_array($identifier, $eligible, true)) {
                    return $this->invalid('Covered '.$dimension.' identifier is not present in the eligible inventory: '.$identifier.'.');
                }
            }

            $covered = count($coveredInventory);
            $total = count($eligible);
            $percentages[$dimension] = 0 === $total ? 100.0 : 100.0 * $covered / $total;
            $details[] = sprintf('%s %d/%d (%.1f%%; target %.0f%%)', $dimension, $covered, $total, $percentages[$dimension], $threshold);
        }

        if ($this->isStale($context, $generatedAtTime)) {
            return $this->result('warning', 'Behavioral/UI coverage evidence is stale relative to application source/UI surfaces.', $details, 'warning');
        }

        $highDebt = false;
        foreach (self::HIGH_DEBT as $dimension => $threshold) {
            if ($percentages[$dimension] < $threshold) {
                $highDebt = true;
                break;
            }
        }

        $passes = array_all(self::THRESHOLDS, fn ($threshold, $dimension): bool => !($percentages[$dimension] < $threshold));

        $summary = 'Behavioral/UI coverage: '.implode('; ', $details).($highDebt ? '; HIGH_BEHAVIORAL_TEST_DEBT' : '').'.';
        if ($passes) {
            return $this->result('passed', $summary);
        }

        if ($highDebt) {
            array_unshift($details, 'HIGH_BEHAVIORAL_TEST_DEBT: repository is eligible for behavioral/UI test remediation.');
        }

        return $this->result('warning', $summary, $details, 'warning');
    }

    private function invalid(string $message): RuleResult
    {
        return $this->result('warning', $message, [], 'warning');
    }

    /**
     * Confirms that the declared evidence producer is executable through a repository-owned package script.
     */
    private function isDeclaredProducerScript(RuleContext $context, string $script): bool
    {
        foreach (['composer.json', 'package.json'] as $manifest) {
            $path = $context->targetPath.'/'.$manifest;
            if (!is_file($path)) {
                continue;
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded) && is_array($decoded['scripts'] ?? null) && is_string($decoded['scripts'][$script] ?? null) && '' !== trim($decoded['scripts'][$script])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Accepts only stable non-empty string identifiers so numerator and denominator remain auditable.
     */
    private function isStringList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        return array_all($value, fn ($identifier): bool => is_string($identifier) && '' !== trim($identifier));
    }

    private function isSymfonyApplication(RuleContext $context): bool
    {
        $composerPath = $context->targetPath.'/composer.json';
        if (is_file($composerPath)) {
            $composer = json_decode((string) file_get_contents($composerPath), true);
            if (is_array($composer)) {
                $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
                if (array_key_exists('symfony/framework-bundle', $require)) {
                    return true;
                }
            }
        }

        return is_file($context->targetPath.'/bin/console')
            && is_file($context->targetPath.'/config/bundles.php')
            && (is_file($context->targetPath.'/app/Kernel.php') || is_file($context->targetPath.'/src/Kernel.php'));
    }

    private function isStale(RuleContext $context, int $generatedAtTime): bool
    {
        foreach (['src', 'templates', 'assets'] as $directory) {
            $root = $context->targetPath.'/'.$directory;
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getMTime() > $generatedAtTime) {
                    return true;
                }
            }
        }

        return false;
    }
}
