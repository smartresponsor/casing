<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Runner;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Kernel\GateKernel;
use Gating\Gate\Policy\ExitCode;
use Gating\Gate\Policy\RulePolicy;
use Gating\Gate\Profile\ProfileLoader;
use Gating\Gate\Registry\RuleRegistry;
use Gating\Gate\Reporter\ConsoleReporter;
use Gating\Gate\Service\PolicyExportService;
use Gating\Gate\Target\TargetDiscovery;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides the gating runner implementation used by the Gating runtime and rule execution flow.
 */
final readonly class GatingRunner
{
    /**
     * Initializes the collaborators and immutable state required by this Gating component.
     */
    public function __construct(
        private string $repoRoot,
        private RuleRegistry $registry = new RuleRegistry(),
        private ProfileLoader $profileLoader = new ProfileLoader(),
        private ConsoleReporter $reporter = new ConsoleReporter(),
        private TargetDiscovery $targetDiscovery = new TargetDiscovery(),
    ) {
    }

    /**
     * Runs check as part of the current Gating command workflow.
     */
    public function runCheck(InputInterface $input, OutputInterface $output, string $commandName = 'check'): int
    {
        return $this->runCheckWithOptions($this->collectCheckOptions($input), $commandName);
    }

    /**
     * Runs discover targets as part of the current Gating command workflow.
     */
    public function runDiscoverTargets(InputInterface $input, OutputInterface $output): int
    {
        $rootPath = $this->optionString($input, 'root', getcwd() ?: $this->repoRoot) ?? $this->repoRoot;
        $maxDepth = $this->optionInt($input, 'max-depth', 2);
        $format = $this->optionString($input, 'format', 'text') ?? 'text';
        if ($this->optionBool($input, 'json')) {
            $format = 'json';
        }

        try {
            $targets = $this->targetDiscovery->discover($rootPath, $maxDepth);
        } catch (\Throwable $error) {
            fwrite(STDERR, '[gating] '.$error->getMessage().PHP_EOL);

            return ExitCode::USAGE_ERROR;
        }

        $payload = [
            'schema' => 'gating.target.discovery.v1',
            'tool' => 'gating/gate',
            'generated_at' => gmdate('c'),
            'root' => realpath($rootPath) ?: $rootPath,
            'max_depth' => $maxDepth,
            'summary' => [
                'total' => count($targets),
                'with_composer' => count(array_filter($targets, static fn (array $target): bool => true === $target['has_composer'])),
                'with_src' => count(array_filter($targets, static fn (array $target): bool => true === $target['has_src'])),
                'with_gating' => count(array_filter($targets, static fn (array $target): bool => true === $target['has_gating'])),
            ],
            'targets' => $targets,
        ];

        if ('json' === $format) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

            return ExitCode::PASSED;
        }

        echo sprintf('Gating target discovery: %d target(s) under %s', count($targets), $payload['root']).PHP_EOL;
        foreach ($targets as $targetItem) {
            echo sprintf(
                '- %s composer=%s src=%s .gating=%s profile=%s',
                $targetItem['relative_path'],
                $targetItem['has_composer'] ? 'yes' : 'no',
                $targetItem['has_src'] ? 'yes' : 'no',
                $targetItem['has_gating'] ? 'yes' : 'no',
                $targetItem['profile_candidate'] ?? 'none'
            ).PHP_EOL;
        }

        return ExitCode::PASSED;
    }

    /**
     * Runs baseline as part of the current Gating command workflow.
     */
    public function runBaseline(InputInterface $input, OutputInterface $output): int
    {
        $rootPath = $this->optionString($input, 'root', getcwd() ?: $this->repoRoot) ?? $this->repoRoot;
        $profilePath = $this->optionString($input, 'profile');
        $reportFile = $this->optionString($input, 'report-file');
        $ruleSetPath = $this->optionString($input, 'rule-set');
        $severityConfigPath = $this->optionString($input, 'severity-config');
        $format = $this->optionString($input, 'format', 'text') ?? 'text';
        if ($this->optionBool($input, 'json')) {
            $format = 'json';
        }
        $maxDepth = $this->optionInt($input, 'max-depth', 2);

        try {
            $targets = $this->targetDiscovery->discover($rootPath, $maxDepth);
        } catch (\Throwable $error) {
            fwrite(STDERR, '[gating] '.$error->getMessage().PHP_EOL);

            return ExitCode::USAGE_ERROR;
        }

        $ruleSet = $this->loadRuleSet($ruleSetPath);
        $rules = $this->registry->only($ruleSet);
        $kernel = new GateKernel($rules);

        try {
            $severityConfig = $this->profileLoader->load($severityConfigPath);
        } catch (\Throwable $error) {
            fwrite(STDERR, '[gating] '.$error->getMessage().PHP_EOL);

            return ExitCode::USAGE_ERROR;
        }

        $items = [];
        foreach ($targets as $targetItem) {
            $candidateProfile = $profilePath ?: ($targetItem['profile_candidate'] ?? null);
            if (null === $candidateProfile) {
                $items[] = [
                    'target' => $targetItem,
                    'status' => 'skipped',
                    'summary' => ['total' => 0, 'passed' => 0, 'failed' => 0, 'skipped' => 0],
                    'message' => 'No profile candidate was found for this target.',
                    'results' => [],
                ];
                continue;
            }

            try {
                $profile = $this->profileLoader->load($candidateProfile);
                $results = $kernel->check(new RuleContext($targetItem['path'], $profile));
                $results = RulePolicy::fromConfigAndProfile($severityConfig, $profile)->apply($results);
            } catch (\Throwable $error) {
                $items[] = [
                    'target' => $targetItem,
                    'status' => 'failed',
                    'summary' => ['total' => 0, 'passed' => 0, 'failed' => 1, 'skipped' => 0],
                    'message' => $error->getMessage(),
                    'results' => [],
                ];
                continue;
            }

            $decoded = json_decode($this->reporter->toJson($results, $targetItem['path'], $profile), true);
            $items[] = [
                'target' => $targetItem,
                'profile_path' => $candidateProfile,
                'status' => $decoded['status'] ?? 'failed',
                'summary' => $decoded['summary'] ?? [],
                'results' => $decoded['results'] ?? [],
            ];
        }

        $failed = count(array_filter($items, static fn (array $item): bool => 'failed' === $item['status']));
        $payload = [
            'schema' => 'gating.baseline.v1',
            'tool' => 'gating/gate',
            'generated_at' => gmdate('c'),
            'root' => realpath($rootPath) ?: $rootPath,
            'summary' => [
                'targets' => count($items),
                'failed' => $failed,
                'passed' => count(array_filter($items, static fn (array $item): bool => 'passed' === $item['status'])),
                'skipped' => count(array_filter($items, static fn (array $item): bool => 'skipped' === $item['status'])),
                'warning' => count(array_filter($items, static fn (array $item): bool => 'warning' === $item['status'])),
                'suppressed' => count(array_filter($items, static fn (array $item): bool => 'suppressed' === $item['status'])),
            ],
            'items' => $items,
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        if (null !== $reportFile) {
            $this->writeString($reportFile, $encoded);
        }

        if ('json' === $format || null === $reportFile) {
            echo $encoded;
        } else {
            echo sprintf('Gating baseline: %d target(s), %d failed, report=%s', count($items), $failed, $reportFile).PHP_EOL;
        }

        return $failed > 0 ? ExitCode::POLICY_FAILED : ExitCode::PASSED;
    }

    /**
     * Runs list rules as part of the current Gating command workflow.
     */
    public function runListRules(InputInterface $input, OutputInterface $output): int
    {
        $format = $this->optionString($input, 'format', 'text') ?? 'text';
        if ($this->optionBool($input, 'json')) {
            $format = 'json';
        }

        $catalog = [
            'schema' => 'gating.rule.catalog.v1',
            'tool' => 'gating/gate',
            'rules' => $this->registry->catalog(),
        ];

        if ('json' === $format) {
            echo json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

            return ExitCode::PASSED;
        }

        foreach ($this->registry->catalog() as $rule) {
            echo sprintf('%s [%s/%s] - %s', $rule['id'], $rule['area'], $rule['scope'], $rule['summary']).PHP_EOL;
        }

        return ExitCode::PASSED;
    }

    /**
     * Runs self check as part of the current Gating command workflow.
     */
    public function runSelfCheck(InputInterface $input, OutputInterface $output): int
    {
        $repoPolicyRoot = $this->repoRoot.'/.gating';
        $policyRoot = $this->optionString($input, 'policy-root') ?: $repoPolicyRoot;

        $defaults = [
            'target' => $policyRoot,
            'profile' => $this->optionString($input, 'profile') ?? $policyRoot.'/profile/component/gating.yaml',
            'rule-set' => $this->optionString($input, 'rule-set') ?? $policyRoot.'/profile/rule-set/local-dev.yaml',
            'report-file' => $this->optionString($input, 'report-file') ?? $policyRoot.'/report/gating-self-report-w11.json',
            'severity-config' => $this->optionString($input, 'severity-config') ?? $policyRoot.'/config/severity.yaml',
            'policy-root' => $policyRoot,
            'root' => $this->optionString($input, 'root') ?? $this->repoRoot,
            'max-depth' => $this->optionInt($input, 'max-depth', 2),
            'json' => $this->optionBool($input, 'json'),
            'format' => $this->optionString($input, 'format', 'text') ?? 'text',
        ];

        return $this->runCheckWithOptions($defaults, 'check');
    }

    /**
     * Runs exit codes as part of the current Gating command workflow.
     */
    public function runExitCodes(InputInterface $input, OutputInterface $output): int
    {
        echo "Gating exit codes:\n";
        echo "  0 passed: no error-severity failure was found.\n";
        echo "  1 policy failed: one or more error-severity rules failed.\n";
        echo "  2 usage error: invalid command, missing target/profile, unreadable config.\n";
        echo "  3 internal error: unexpected runtime failure.\n";

        return ExitCode::PASSED;
    }

    /**
     * Runs export policy as part of the current Gating command workflow.
     */
    public function runExportPolicy(InputInterface $input, OutputInterface $output, PolicyExportService $exporter): int
    {
        $policyRoot = $this->optionString($input, 'policy-root', $this->repoRoot.'/.gating') ?? $this->repoRoot.'/.gating';
        $outputFile = $this->optionString($input, 'output');
        $pretty = $this->optionBool($input, 'pretty') || $this->optionBool($input, 'json');

        try {
            $export = $exporter->export($this->repoRoot, $policyRoot);
        } catch (\Throwable $error) {
            fwrite(STDERR, '[gating] '.$error->getMessage().PHP_EOL);

            return ExitCode::USAGE_ERROR;
        }

        $artifact = $export['artifact'];
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($artifact, $flags | JSON_THROW_ON_ERROR).PHP_EOL;
        if (null !== $outputFile) {
            $this->writeString($outputFile, $json);
        }

        if (null === $outputFile || $pretty || $this->optionBool($input, 'json')) {
            echo $json;
        } else {
            echo $artifact['content_hash'].PHP_EOL;
        }

        return ExitCode::PASSED;
    }

    /**
     * Finds policy root from the repository state available to Gating.
     */
    private function findPolicyRoot(string $startPath): ?string
    {
        $current = realpath($startPath) ?: $startPath;
        if (is_file($current)) {
            $current = dirname($current);
        }

        while (true) {
            $candidate = $current.DIRECTORY_SEPARATOR.'.gating';
            if (is_dir($candidate)) {
                return $candidate;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        return null;
    }

    /** @return list<string> */
    private function loadRuleSet(?string $ruleSetPath): array
    {
        if (null === $ruleSetPath || '' === $ruleSetPath) {
            return [];
        }

        $ruleSetProfile = $this->profileLoader->load($ruleSetPath);
        $declaredRules = $ruleSetProfile['rule_set']['rules'] ?? [];
        if (!is_array($declaredRules)) {
            return [];
        }

        return array_values(array_filter($declaredRules, is_string(...)));
    }

    /**
     * Discovers profile candidate from the repository state available to Gating.
     */
    private function discoverProfileCandidate(string $targetPath): ?string
    {
        $baseName = basename($targetPath);
        $localProfile = $targetPath.DIRECTORY_SEPARATOR.'.gating'.DIRECTORY_SEPARATOR.'profile'.DIRECTORY_SEPARATOR.'component'.DIRECTORY_SEPARATOR.strtolower($baseName).'.yaml';
        if (is_file($localProfile)) {
            return $localProfile;
        }

        $template = $targetPath.DIRECTORY_SEPARATOR.'.gating'.DIRECTORY_SEPARATOR.'profile'.DIRECTORY_SEPARATOR.'component'.DIRECTORY_SEPARATOR.'_template.yaml';
        if (is_file($template)) {
            return $template;
        }

        return null;
    }

    /**
     * @return array{
     *     target: string,
     *     root: string,
     *     profile: ?string,
     *     format: string,
     *     json: bool,
     *     report-file: ?string,
     *     rule-set: ?string,
     *     severity-config: ?string,
     *     policy-root: ?string,
     *     max-depth: int
     * }
     */
    private function collectCheckOptions(InputInterface $input): array
    {
        $cwd = getcwd() ?: $this->repoRoot;

        return [
            'target' => $this->optionString($input, 'target', $cwd) ?? $cwd,
            'root' => $this->optionString($input, 'root', $cwd) ?? $cwd,
            'profile' => $this->optionString($input, 'profile'),
            'format' => $this->optionString($input, 'format', 'text') ?? 'text',
            'json' => $this->optionBool($input, 'json'),
            'report-file' => $this->optionString($input, 'report-file'),
            'rule-set' => $this->optionString($input, 'rule-set'),
            'severity-config' => $this->optionString($input, 'severity-config'),
            'policy-root' => $this->optionString($input, 'policy-root'),
            'max-depth' => $this->optionInt($input, 'max-depth', 2),
        ];
    }

    /**
     * @param array{
     *     target: string,
     *     root: string,
     *     profile: ?string,
     *     format: string,
     *     json: bool,
     *     report-file: ?string,
     *     rule-set: ?string,
     *     severity-config: ?string,
     *     policy-root: ?string,
     *     max-depth: int
     * } $options
     */
    private function runCheckWithOptions(array $options, string $commandName): int
    {
        $repoPolicyRoot = $this->repoRoot.'/.gating';
        $policyRoot = $options['policy-root'] ?: $this->findPolicyRoot($options['target']) ?: $this->findPolicyRoot($options['root']) ?: $repoPolicyRoot;
        $severityConfigPath = $options['severity-config'] ?: $policyRoot.'/config/severity.yaml';

        $targetReal = realpath($options['target']);
        if (false === $targetReal || !is_dir($targetReal)) {
            fwrite(STDERR, sprintf("[gating] target path was not found: %s\n", $options['target']));

            return ExitCode::USAGE_ERROR;
        }

        $profilePath = $options['profile'] ?: $this->discoverProfileCandidate($targetReal);

        try {
            $profile = $this->profileLoader->load($profilePath);
            $severityConfig = $this->profileLoader->load($severityConfigPath);
        } catch (\Throwable $error) {
            fwrite(STDERR, '[gating] '.$error->getMessage().PHP_EOL);

            return ExitCode::USAGE_ERROR;
        }

        $ruleSet = $this->loadRuleSet($options['rule-set']);
        if ([] === $ruleSet) {
            $profileRuleSet = $profile['component']['enabled_rules'] ?? [];
            if (is_array($profileRuleSet)) {
                $ruleSet = array_values(array_filter($profileRuleSet, is_string(...)));
            }
        }
        $rules = $this->registry->only($ruleSet);
        if ([] === $rules) {
            fwrite(STDERR, '[gating] selected rule set did not match any registered rule.'.PHP_EOL);

            return ExitCode::USAGE_ERROR;
        }

        $kernel = new GateKernel($rules);
        $context = new RuleContext($targetReal, $profile);
        $results = $kernel->check($context);
        $results = RulePolicy::fromConfigAndProfile($severityConfig, $profile)->apply($results);

        if (null !== $options['report-file']) {
            $this->writeString($options['report-file'], $this->reporter->toJson($results, $targetReal, $profile));
        }

        $emitJson = $options['json'] || 'json' === $options['format'] || in_array($commandName, ['scan', 'report', 'inventory'], true);
        if ($emitJson) {
            echo $this->reporter->toJson($results, $targetReal, $profile);

            return $this->hasFailure($results) ? ExitCode::POLICY_FAILED : ExitCode::PASSED;
        }

        $exitCode = $this->reporter->printText($results);

        return 0 === $exitCode ? ExitCode::PASSED : ExitCode::POLICY_FAILED;
    }

    /**
     * Executes the option string responsibility defined by this Gating component.
     */
    private function optionString(InputInterface $input, string $name, ?string $default = null): ?string
    {
        if (!$input->hasOption($name)) {
            return $default;
        }

        $value = $input->getOption($name);
        if (is_string($value)) {
            return '' !== $value ? $value : $default;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : $default;
        }

        if (null === $value) {
            return $default;
        }

        return $default;
    }

    /**
     * Executes the option bool responsibility defined by this Gating component.
     */
    private function optionBool(InputInterface $input, string $name): bool
    {
        if (!$input->hasOption($name)) {
            return false;
        }

        return (bool) $input->getOption($name);
    }

    /**
     * Executes the option int responsibility defined by this Gating component.
     */
    private function optionInt(InputInterface $input, string $name, int $default): int
    {
        if (!$input->hasOption($name)) {
            return $default;
        }

        $value = $input->getOption($name);
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_string($value) && '' !== $value) {
            return max(0, (int) $value);
        }

        if (is_float($value)) {
            return max(0, (int) $value);
        }

        return $default;
    }

    /**
     * Writes string to the repository-owned Gating output surface.
     */
    private function writeString(string $path, string $contents): void
    {
        $dir = dirname($path);
        if ('' !== $dir && '.' !== $dir && !is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $contents);
    }

    /**
     * @param list<\Gating\Gate\Contract\RuleResult> $results
     */
    private function hasFailure(array $results): bool
    {
        return array_any($results, fn ($result) => 'failed' === $result->status);
    }
}
