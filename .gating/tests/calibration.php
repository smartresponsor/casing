<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Inventory\InventoryScanner;
use Gating\Gate\Rule\Canon\Canon027DatabaseEngineBaselineRule;
use Gating\Gate\Rule\Canon\Canon028DualDoctrineConnectionRule;
use Gating\Gate\Rule\Canon\Canon031PhpDocCoverageRule;
use Gating\Gate\Rule\Canon\Canon032BundleRegistrationRule;
use Gating\Gate\Rule\Canon\Canon035SymfonyContainerReuseRule;
use Gating\Gate\Rule\Canon\Canon036DocumentationProducerOwnershipRule;
use Gating\Gate\Rule\Canon\Canon037GeneratedReferenceArtifactRule;
use Gating\Gate\Rule\Canon\Canon038ConfigYamlSubjectPrefixRule;
use Gating\Gate\Rule\Canon\Canon039PhpTestToolingRule;
use Gating\Gate\Rule\Canon\Canon040PhpTestCoverageRule;
use Gating\Gate\Rule\Canon\Canon041BehavioralUiTestToolingRule;
use Gating\Gate\Rule\Canon\Canon042BehavioralUiCoverageRule;
use Gating\Gate\Rule\Canon\Canon043DevelopmentComposerDependencyVersionRule;
use Gating\Gate\Rule\Canon\Canon044ObjectingSystemFieldNamingRule;
use Gating\Gate\Rule\Canon\Canon045DevelopmentComposerRepositoryClosureRule;
use Gating\Gate\Rule\Canon\CanonRuleMirrorRule;
use Gating\Gate\Rule\Documentation\DocblockPreservationRule;
use Gating\Gate\Rule\Mirror\ServiceInterfaceMirrorRule;
use Gating\Gate\Rule\Mutation\MutationSafetyRule;
use Gating\Gate\Rule\Route\RouteOwnerRootRule;
use Gating\Gate\Rule\Route\RoutePathSegmentSeparationRule;
use Gating\Gate\Rule\Security\SecretLeakRule;

$root = sys_get_temp_dir().'/gating-calibration-'.bin2hex(random_bytes(4));
mkdir($root, 0777, true);

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

/** @param list<string> $command */
$runCommand = static function (array $command): int {
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        return 127;
    }
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            stream_get_contents($pipe);
            fclose($pipe);
        }
    }

    return proc_close($process);
};

try {
    $mirrorRoot = $root.'/mirror';
    $mirrorGating = $mirrorRoot.'/Gating';
    $mirrorCanonization = $mirrorRoot.'/Canonization/.canonization/Governance/Architecture/Rule';
    mkdir($mirrorGating.'/src/Rule/Canon', 0777, true);
    mkdir($mirrorCanonization, 0777, true);
    file_put_contents($mirrorGating.'/src/Rule/Canon/Canon999EvidenceProbeRule.php', "<?php\n");
    file_put_contents($mirrorCanonization.'/Canon999EvidenceProbeRule.md', <<<'MARKDOWN'
# Canon999EvidenceProbeRule — Calibration Probe

## Evidence Contract
```yaml
evidence_contract:
  coverage: "probe population"
  extraction: [relative_path]
  body_read: prohibited
  reasoning: none
  escalation: [parse_failure]
  executable_evidence: ["probe gate"]
```
MARKDOWN);
    $mirror = (new CanonRuleMirrorRule())->check(new RuleContext($mirrorGating));
    $assert('passed' === $mirror->status, 'A mirrored Canon rule with a complete Evidence Contract must pass the mirror contract.');

    file_put_contents(
        $mirrorCanonization.'/Canon999EvidenceProbeRule.md',
        str_replace("  reasoning: none\n", '', (string) file_get_contents($mirrorCanonization.'/Canon999EvidenceProbeRule.md')),
    );
    $mirror = (new CanonRuleMirrorRule())->check(new RuleContext($mirrorGating));
    $assert('failed' === $mirror->status && str_contains(implode(' | ', $mirror->evidence), 'missing reasoning'), 'A Canon rule missing an Evidence Contract field must fail the mirror contract.');

    unlink($mirrorCanonization.'/Canon999EvidenceProbeRule.md');
    unlink($mirrorGating.'/src/Rule/Canon/Canon999EvidenceProbeRule.php');
    rmdir($mirrorCanonization);
    rmdir(dirname($mirrorCanonization));
    rmdir(dirname($mirrorCanonization, 2));
    rmdir(dirname($mirrorCanonization, 3));
    rmdir(dirname($mirrorCanonization, 4));
    rmdir($mirrorGating.'/src/Rule/Canon');
    rmdir($mirrorGating.'/src/Rule');
    rmdir($mirrorGating.'/src');
    rmdir($mirrorGating);
    rmdir($mirrorRoot);

    mkdir($root.'/config/routes', 0777, true);
    file_put_contents($root.'/config/routes/test.yaml', <<<'YAML'
real_route:
  path: /interfacing/route-audit
  controller: App\Service\ExampleService
redirect_route:
  path: /app/redirect
  controller: Symfony\Bundle\FrameworkBundle\Controller\RedirectController
  defaults:
    path: /interfacing/
YAML);

    $profile = ['component' => ['route_owner_roots' => ['app', 'interfacing']]];
    $owner = (new RouteOwnerRootRule())->check(new RuleContext($root, $profile));
    $assert('passed' === $owner->status, 'Allowed interfacing owner root must pass.');
    $segments = (new RoutePathSegmentSeparationRule())->check(new RuleContext($root, $profile));
    $assert('failed' === $segments->status, 'Joined route path segment must fail.');
    $assert(0 === count(array_filter($segments->evidence, static fn (string $evidence): bool => str_contains($evidence, '/interfacing/') && !str_contains($evidence, 'route-audit'))), 'Nested defaults.path must not be parsed as a route.');

    file_put_contents($root.'/config/routes/vendor.yaml', "vendor_login:\n  path: /vendor_login\n");
    $excludedProfile = ['component' => [
        'route_owner_roots' => ['app', 'interfacing'],
        'route_excluded_names' => ['vendor_login'],
    ]];
    $owner = (new RouteOwnerRootRule())->check(new RuleContext($root, $excludedProfile));
    $assert('passed' === $owner->status, 'Excluded vendor route name must not affect owner policy.');
    $segments = (new RoutePathSegmentSeparationRule())->check(new RuleContext($root, $excludedProfile));
    $assert('failed' === $segments->status && 1 === count($segments->evidence), 'Excluded vendor route name must not add path-segment findings.');

    $composerPolicyRoot = $root.'/composer-policy';
    $composerPolicySibling = $root.'/Objecting';
    mkdir($composerPolicyRoot, 0777, true);
    mkdir($composerPolicySibling, 0777, true);
    file_put_contents($composerPolicySibling.'/composer.json', '{"name":"objecting/object"}');
    file_put_contents($composerPolicyRoot.'/composer.json', json_encode([
        'minimum-stability' => 'dev',
        'prefer-stable' => true,
        'require' => ['objecting/object' => 'dev-master'],
        'repositories' => [[
            'type' => 'path',
            'url' => '../Objecting',
            'options' => ['symlink' => true, 'versions' => ['objecting/object' => 'dev-master']],
        ]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $composerVersion = (new Canon043DevelopmentComposerDependencyVersionRule())->check(new RuleContext($composerPolicyRoot));
    $assert('passed' === $composerVersion->status, 'Local sibling dependency using dev-master must pass Canon043.');
    file_put_contents($composerPolicyRoot.'/composer.json', str_replace('dev-master', '*@dev', (string) file_get_contents($composerPolicyRoot.'/composer.json')));
    $composerVersion = (new Canon043DevelopmentComposerDependencyVersionRule())->check(new RuleContext($composerPolicyRoot));
    $assert('failed' === $composerVersion->status, 'Local sibling dependency using *@dev must fail Canon043.');

    $composerClosureRoot = $root.'/composer-closure';
    $composerClosureCruding = $root.'/Cruding';
    $composerClosureCollectioning = $root.'/Collectioning';
    mkdir($composerClosureRoot, 0777, true);
    mkdir($composerClosureCruding, 0777, true);
    mkdir($composerClosureCollectioning, 0777, true);
    file_put_contents($composerClosureCollectioning.'/composer.json', '{"name":"collectioning/collection"}');
    file_put_contents($composerClosureCruding.'/composer.json', json_encode([
        'name' => 'cruding/crud',
        'require' => ['collectioning/collection' => 'dev-master'],
        'repositories' => [['type' => 'path', 'url' => '../Collectioning']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($composerClosureRoot.'/composer.json', json_encode([
        'require' => ['cruding/crud' => 'dev-master'],
        'repositories' => [['type' => 'path', 'url' => '../Cruding']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $composerClosure = (new Canon045DevelopmentComposerRepositoryClosureRule())->check(new RuleContext($composerClosureRoot));
    $assert('failed' === $composerClosure->status, 'Missing root path visibility for a transitive first-party dependency must fail Canon045.');
    file_put_contents($composerClosureRoot.'/composer.json', json_encode([
        'require' => ['cruding/crud' => 'dev-master'],
        'repositories' => [
            ['type' => 'path', 'url' => '../Cruding'],
            ['type' => 'path', 'url' => '../Collectioning'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $composerClosure = (new Canon045DevelopmentComposerRepositoryClosureRule())->check(new RuleContext($composerClosureRoot));
    $assert('passed' === $composerClosure->status, 'Complete root local repository closure must pass Canon045.');

    $objectingNamingRoot = $root.'/objecting-field-naming';
    mkdir($objectingNamingRoot.'/src/Entity', 0777, true);
    file_put_contents($objectingNamingRoot.'/composer.json', '{"name":"objecting/object"}');
    file_put_contents($objectingNamingRoot.'/src/Entity/Example.php', "<?php\nnamespace App\\Objecting\\Entity;\nuse Doctrine\\ORM\\Mapping as ORM;\nfinal class Example { #[ORM\\Column(name: 'created_at')] private \\DateTimeImmutable \$createdAt; }\n");
    $objectingNaming = (new Canon044ObjectingSystemFieldNamingRule())->check(new RuleContext($objectingNamingRoot));
    $assert('passed' === $objectingNaming->status, 'Entity-native Objecting system field names must pass Canon044.');
    file_put_contents($objectingNamingRoot.'/src/Entity/Example.php', "<?php\nnamespace App\\Objecting\\Entity;\nuse Doctrine\\ORM\\Mapping as ORM;\nfinal class Example { #[ORM\\Column(name: 'object_created_at')] private \\DateTimeImmutable \$objectCreatedAt; }\n");
    $objectingNaming = (new Canon044ObjectingSystemFieldNamingRule())->check(new RuleContext($objectingNamingRoot));
    $assert('failed' === $objectingNaming->status, 'Objecting-prefixed Doctrine field and column names must fail Canon044.');
    unlink($objectingNamingRoot.'/src/Entity/Example.php');
    unlink($objectingNamingRoot.'/composer.json');
    rmdir($objectingNamingRoot.'/src/Entity');
    rmdir($objectingNamingRoot.'/src');
    rmdir($objectingNamingRoot);

    $phpDocRoot = $root.'/phpdoc-coverage';
    mkdir($phpDocRoot.'/src', 0777, true);
    file_put_contents($phpDocRoot.'/src/ExampleDocumentedService.php', <<<'PHPFILE'
<?php
/**
 * Represents one documented service contract used for calibration.
 */
final class ExampleDocumentedService
{
    public function __construct() {}
    public function getName(): string { return 'example'; }
    public function setName(string $name): void {}
    private function helper(): void {}
    /**
     * Executes the externally meaningful service behavior for callers.
     */
    public function run(): void {}
}
PHPFILE);
    $phpDocCoverage = (new Canon031PhpDocCoverageRule())->check(new RuleContext($phpDocRoot));
    $assert('passed' === $phpDocCoverage->status, 'Canon031 must ignore constructor/accessor/private implementation noise when the class and contract method are documented.');
    $assert(str_contains($phpDocCoverage->message, 'excluded trivial/internal methods'), 'Canon031 must report excluded trivial/internal methods separately from the coverage denominator.');
    unlink($phpDocRoot.'/src/ExampleDocumentedService.php');
    rmdir($phpDocRoot.'/src');
    rmdir($phpDocRoot);

    file_put_contents($root.'/.php-cs-fixer.dist.php', "<?php return (new PhpCsFixer\\Config())->setRules(['phpdoc_to_comment' => false]);\n");
    $docblock = (new DocblockPreservationRule())->check(new RuleContext($root));
    $assert('passed' === $docblock->status, 'Disabled phpdoc_to_comment must pass.');
    file_put_contents($root.'/.php-cs-fixer.dist.php', "<?php return (new PhpCsFixer\\Config())->setRules(['phpdoc_to_comment' => true]);\n");
    $docblock = (new DocblockPreservationRule())->check(new RuleContext($root));
    $assert('failed' === $docblock->status, 'Enabled phpdoc_to_comment must fail.');

    mkdir($root.'/config/packages', 0777, true);
    file_put_contents($root.'/config/packages/framework.yaml', "parameters:\n  app_password: '%app_password%'\nframework:\n  secret: '%env(APP_SECRET)%'\n");

    mkdir($root.'/bin', 0777, true);
    file_put_contents($root.'/bin/console', "#!/usr/bin/env php\n");
    file_put_contents($root.'/config/bundles.php', "<?php return [];\n");
    file_put_contents($root.'/config/packages/doctrine.yaml', <<<'YAML'
doctrine:
  dbal:
    connections:
      data:
        driver: pdo_pgsql
      infra:
        driver: pdo_sqlite
YAML);
    $databaseEngines = (new Canon027DatabaseEngineBaselineRule())->check(new RuleContext($root));
    $assert('passed' === $databaseEngines->status, 'PostgreSQL and SQLite must pass the canonical engine baseline.');
    $databaseConnections = (new Canon028DualDoctrineConnectionRule())->check(new RuleContext($root));
    $assert('passed' === $databaseConnections->status, 'data/PostgreSQL and infra/SQLite must pass connection topology.');

    file_put_contents($root.'/config/packages/doctrine.yaml', str_replace('pdo_sqlite', 'pdo_mysql', (string) file_get_contents($root.'/config/packages/doctrine.yaml')));
    $databaseEngines = (new Canon027DatabaseEngineBaselineRule())->check(new RuleContext($root));
    $assert('failed' === $databaseEngines->status, 'MySQL must fail the PostgreSQL and SQLite engine baseline.');

    $appKernelRoot = $root.'/app-kernel';
    mkdir($appKernelRoot.'/app', 0777, true);
    mkdir($appKernelRoot.'/bin', 0777, true);
    mkdir($appKernelRoot.'/config', 0777, true);
    mkdir($appKernelRoot.'/src', 0777, true);
    file_put_contents($appKernelRoot.'/composer.json', '{"require":{"symfony/framework-bundle":"^8.1"}}');
    file_put_contents($appKernelRoot.'/bin/console', "#!/usr/bin/env php\n");
    file_put_contents($appKernelRoot.'/app/Kernel.php', "<?php namespace App; final class Kernel {}\n");
    file_put_contents($appKernelRoot.'/src/ExampleBundle.php', "<?php\nnamespace App\\Example;\nfinal class ExampleBundle {}\n");
    file_put_contents($appKernelRoot.'/config/bundles.php', "<?php return [App\\Example\\ExampleBundle::class => ['all' => true]];\n");
    $bundleRegistration = (new Canon032BundleRegistrationRule())->check(new RuleContext($appKernelRoot));
    $assert('passed' === $bundleRegistration->status, 'Canonical app/Kernel.php standalone mode must be recognized by Canon032; got '.$bundleRegistration->status.': '.$bundleRegistration->message.' '.implode(' | ', $bundleRegistration->evidence));
    $containerReuse = (new Canon035SymfonyContainerReuseRule())->check(new RuleContext($appKernelRoot));
    $assert('passed' === $containerReuse->status, 'Canonical app/Kernel.php standalone mode must be recognized by Canon035; got '.$containerReuse->status.': '.$containerReuse->message.' '.implode(' | ', $containerReuse->evidence));
    unlink($appKernelRoot.'/composer.json');
    unlink($appKernelRoot.'/bin/console');
    unlink($appKernelRoot.'/app/Kernel.php');
    unlink($appKernelRoot.'/src/ExampleBundle.php');
    unlink($appKernelRoot.'/config/bundles.php');
    rmdir($appKernelRoot.'/app');
    rmdir($appKernelRoot.'/bin');
    rmdir($appKernelRoot.'/config');
    rmdir($appKernelRoot.'/src');
    rmdir($appKernelRoot);

    $yamlRoot = $root.'/yaml-prefix';
    mkdir($yamlRoot.'/config/packages', 0777, true);
    mkdir($yamlRoot.'/config/routes', 0777, true);
    file_put_contents($yamlRoot.'/composer.json', <<<'JSON'
{
  "name": "cataloging/catalog",
  "autoload": {"psr-4": {"App\\Cataloging\\": "src/"}}
}
JSON);
    file_put_contents($yamlRoot.'/config/packages/catalog_cache_policy.yaml', "framework: {}\n");
    file_put_contents($yamlRoot.'/config/packages/security.yml', "security: {}\n");
    file_put_contents($yamlRoot.'/config/packages/web_profiler.yaml', "web_profiler: {}\n");
    file_put_contents($yamlRoot.'/config/routes/easyadmin.yaml', "easyadmin: {}\n");
    file_put_contents($yamlRoot.'/config/routes/catalog_admin.yaml', "catalog_admin: { path: /catalog }\n");
    $yamlPrefix = (new Canon038ConfigYamlSubjectPrefixRule())->check(new RuleContext($yamlRoot));
    $assert('passed' === $yamlPrefix->status, 'Subject-prefixed component YAML and conventional bootstrap YAML must pass Canon038.');

    file_put_contents($yamlRoot.'/config/packages/faceting_security.yaml', "security: {}\n");
    $yamlPrefix = (new Canon038ConfigYamlSubjectPrefixRule())->check(new RuleContext($yamlRoot));
    $assert('failed' === $yamlPrefix->status, 'A component-token or foreign-prefix YAML filename must fail Canon038.');
    unlink($yamlRoot.'/config/packages/faceting_security.yaml');

    file_put_contents($yamlRoot.'/config/packages/doctrine_catalog.yaml', "doctrine: {}\n");
    $yamlPrefix = (new Canon038ConfigYamlSubjectPrefixRule())->check(new RuleContext($yamlRoot));
    $assert('failed' === $yamlPrefix->status, 'Subject vocabulary after the semantic filename must fail the left-edge Canon038 prefix contract.');
    unlink($yamlRoot.'/config/packages/doctrine_catalog.yaml');

    $testingRoot = $root.'/php-test-coverage';
    mkdir($testingRoot.'/src', 0777, true);
    mkdir($testingRoot.'/var/coverage', 0777, true);
    file_put_contents($testingRoot.'/src/ExampleService.php', '<?php namespace App\\Service; final class ExampleService { public function run(bool $flag): int { return $flag ? 1 : 0; } }'."\n");
    file_put_contents($testingRoot.'/composer.json', json_encode([
        'require' => ['php' => '^8.4'],
        'require-dev' => ['phpunit/phpunit' => '^13.3.3'],
        'scripts' => [
            'test' => 'phpunit',
            'test:coverage' => 'phpunit --branch-coverage --coverage-text=var/coverage/summary.txt',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($testingRoot.'/phpunit.xml.dist', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <source>
    <include>
      <directory suffix=".php">src</directory>
    </include>
  </source>
  <coverage includeUncoveredFiles="true" branchCoverage="true">
    <report>
      <text outputFile="var/coverage/summary.txt" showOnlySummary="true"/>
    </report>
  </coverage>
</phpunit>
XML);
    $testTooling = (new Canon039PhpTestToolingRule())->check(new RuleContext($testingRoot));
    $assert('passed' === $testTooling->status, 'Canonical PHPUnit dependency/config/scripts must pass Canon039; got '.$testTooling->status.': '.$testTooling->message.' '.implode(' | ', $testTooling->evidence));

    file_put_contents($testingRoot.'/var/coverage/summary.txt', <<<'COVERAGE'
Code Coverage Report:
  Methods: 80.00% (4/5)
  Branches: 70.00% (7/10)
  Lines: 80.00% (8/10)
COVERAGE);
    touch($testingRoot.'/var/coverage/summary.txt', time() + 5);
    $testCoverage = (new Canon040PhpTestCoverageRule())->check(new RuleContext($testingRoot));
    $assert('passed' === $testCoverage->status, 'Exactly 80/80/70 coverage must pass Canon040; got '.$testCoverage->status.': '.$testCoverage->message.' '.implode(' | ', $testCoverage->evidence));

    file_put_contents($testingRoot.'/var/coverage/summary.txt', <<<'COVERAGE'
Code Coverage Report:
  Methods: 40.00% (2/5)
  Branches: 30.00% (3/10)
  Lines: 40.00% (4/10)
COVERAGE);
    touch($testingRoot.'/var/coverage/summary.txt', time() + 5);
    $testCoverage = (new Canon040PhpTestCoverageRule())->check(new RuleContext($testingRoot));
    $assert('warning' === $testCoverage->status && str_contains($testCoverage->message, 'HIGH_TEST_DEBT'), '40/40/30 coverage must warn and classify HIGH_TEST_DEBT.');

    file_put_contents($testingRoot.'/var/coverage/summary.txt', <<<'COVERAGE'
Code Coverage Report:
  Methods: 100.00% (5/5)
  Lines: 100.00% (10/10)
COVERAGE);
    touch($testingRoot.'/var/coverage/summary.txt', time() + 5);
    $testCoverage = (new Canon040PhpTestCoverageRule())->check(new RuleContext($testingRoot));
    $assert('warning' === $testCoverage->status && str_contains(implode(' | ', $testCoverage->evidence), 'Missing Branches metric'), 'Missing branch instrumentation must warn instead of being treated as full branch coverage.');

    $uiTestingRoot = $root.'/behavioral-ui-testing';
    mkdir($uiTestingRoot.'/src', 0777, true);
    mkdir($uiTestingRoot.'/var/coverage', 0777, true);
    file_put_contents($uiTestingRoot.'/src/Kernel.php', "<?php namespace App; final class Kernel {}\n");
    file_put_contents($uiTestingRoot.'/composer.json', json_encode([
        'require' => ['symfony/framework-bundle' => '^8.1'],
        'require-dev' => [
            'phpunit/phpunit' => '^13.3',
            'symfony/test-pack' => '^2.0',
            'symfony/panther' => '^2.3',
        ],
        'scripts' => [
            'test' => 'phpunit',
            'test:behavioral-coverage' => 'php tool/behavioral-coverage.php',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($uiTestingRoot.'/package.json', json_encode([
        'devDependencies' => ['@playwright/test' => '^1.0'],
        'scripts' => ['test:ui' => 'playwright test'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($uiTestingRoot.'/playwright.config.ts', "export default {};\n");

    $behavioralTooling = (new Canon041BehavioralUiTestToolingRule())->check(new RuleContext($uiTestingRoot));
    $assert('passed' === $behavioralTooling->status, 'Canonical Symfony Test Pack, Panther, and Playwright tooling must pass Canon041; got '.$behavioralTooling->status.': '.$behavioralTooling->message.' '.implode(' | ', $behavioralTooling->evidence));

    file_put_contents($uiTestingRoot.'/var/coverage/behavioral-ui.json', json_encode([
        'functional' => ['covered' => 8, 'total' => 10],
        'behavioral' => ['covered' => 8, 'total' => 10],
        'ui' => ['covered' => 7, 'total' => 10],
        'critical' => ['covered' => 3, 'total' => 3],
    ], JSON_PRETTY_PRINT));
    $legacyBehavioralCoverage = (new Canon042BehavioralUiCoverageRule())->check(new RuleContext($uiTestingRoot));
    $assert('warning' === $legacyBehavioralCoverage->status && str_contains($legacyBehavioralCoverage->message, 'legacy schema'), 'Counter-only Canon042 evidence must warn instead of being accepted as reproducible coverage.');

    $inventory = static fn (int $total, int $covered, string $prefix): array => [
        'eligible' => array_map(static fn (int $index): string => $prefix.':'.$index, range(1, $total)),
        'covered' => array_map(static fn (int $index): string => $prefix.':'.$index, range(1, $covered)),
    ];
    file_put_contents($uiTestingRoot.'/var/coverage/behavioral-ui.json', json_encode([
        'schema' => 'behavioral-ui-coverage-v2',
        'generatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        'producer' => ['kind' => 'repository_script', 'script' => 'test:behavioral-coverage'],
        'dimensions' => [
            'functional' => $inventory(10, 8, 'functional'),
            'behavioral' => $inventory(10, 8, 'behavioral'),
            'ui' => $inventory(10, 7, 'ui'),
            'critical' => $inventory(3, 3, 'critical'),
        ],
    ], JSON_PRETTY_PRINT));
    $behavioralCoverage = (new Canon042BehavioralUiCoverageRule())->check(new RuleContext($uiTestingRoot));
    $assert('passed' === $behavioralCoverage->status, 'Exactly 80/80/70/100 behavioral/UI coverage must pass Canon042; got '.$behavioralCoverage->status.': '.$behavioralCoverage->message.' '.implode(' | ', $behavioralCoverage->evidence));

    file_put_contents($uiTestingRoot.'/var/coverage/behavioral-ui.json', json_encode([
        'schema' => 'behavioral-ui-coverage-v2',
        'generatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        'producer' => ['kind' => 'repository_script', 'script' => 'test:behavioral-coverage'],
        'dimensions' => [
            'functional' => $inventory(10, 4, 'functional'),
            'behavioral' => $inventory(10, 4, 'behavioral'),
            'ui' => $inventory(10, 3, 'ui'),
            'critical' => $inventory(3, 2, 'critical'),
        ],
    ], JSON_PRETTY_PRINT));
    $behavioralCoverage = (new Canon042BehavioralUiCoverageRule())->check(new RuleContext($uiTestingRoot));
    $assert('warning' === $behavioralCoverage->status && str_contains($behavioralCoverage->message, 'HIGH_BEHAVIORAL_TEST_DEBT'), '40/40/30/66.7 behavioral/UI coverage must warn and classify HIGH_BEHAVIORAL_TEST_DEBT.');

    unlink($uiTestingRoot.'/var/coverage/behavioral-ui.json');
    unlink($uiTestingRoot.'/playwright.config.ts');
    unlink($uiTestingRoot.'/package.json');
    unlink($uiTestingRoot.'/composer.json');
    unlink($uiTestingRoot.'/src/Kernel.php');
    rmdir($uiTestingRoot.'/var/coverage');
    rmdir($uiTestingRoot.'/var');
    rmdir($uiTestingRoot.'/src');
    rmdir($uiTestingRoot);

    unlink($testingRoot.'/var/coverage/summary.txt');
    unlink($testingRoot.'/phpunit.xml.dist');
    unlink($testingRoot.'/composer.json');
    unlink($testingRoot.'/src/ExampleService.php');
    rmdir($testingRoot.'/var/coverage');
    rmdir($testingRoot.'/var');
    rmdir($testingRoot.'/src');
    rmdir($testingRoot);

    unlink($root.'/bin/console');
    unlink($root.'/config/bundles.php');
    mkdir($root.'/public/bundles/vendor', 0777, true);
    $generatedBundleProbe = sprintf("const %s = '%s';\n", implode('', ['api', '_key']), 'abcdefghijklmnopqrstuvwxyz');
    file_put_contents($root.'/public/bundles/vendor/generated.js', $generatedBundleProbe);
    $secret = (new SecretLeakRule())->check(new RuleContext($root));
    $assert('passed' === $secret->status, 'Environment references, Symfony parameter references, and public bundles must not trigger.');
    $probeToken = implode('', ['sk-proj-', 'abcdefghijklmnopqrstuvwxyz1234567890']);
    $secretAssignment = sprintf("%s = '%s'\n", implode('', ['api', '_key']), $probeToken);
    file_put_contents($root.'/leak.txt', $secretAssignment);
    $secret = (new SecretLeakRule())->check(new RuleContext($root));
    $assert('failed' === $secret->status, 'Known-format secret must fail.');
    $assert(!str_contains(implode('\n', $secret->evidence), 'abcdefghijklmnopqrstuvwxyz1234567890'), 'Secret evidence must be redacted.');

    mkdir($root.'/.automation', 0777, true);
    $dangerousMutation = implode('', ['Remove', '-Item -Recurse -Force $target'])."\n";
    file_put_contents($root.'/.automation/cleanup.ps1', $dangerousMutation);
    $mutation = (new MutationSafetyRule())->check(new RuleContext($root));
    $assert('failed' === $mutation->status, 'Broad mutation must fail by default.');
    $mutation = (new MutationSafetyRule())->check(new RuleContext($root, ['component' => [
        'mutation_scan_excluded_paths' => ['.automation/**'],
    ]]));
    $assert('passed' === $mutation->status, 'Explicit component mutation-scan exclusion must suppress only the declared path.');
    unlink($root.'/.automation/cleanup.ps1');
    mkdir($root.'/.gating', 0777, true);
    file_put_contents($root.'/.gating/internal-cleanup.ps1', $dangerousMutation);
    $mutation = (new MutationSafetyRule())->check(new RuleContext($root));
    $assert('passed' === $mutation->status, 'Consumer-local .gating tooling must not be treated as component mutation surface.');

    mkdir($root.'/src/Service/Example', 0777, true);
    mkdir($root.'/src/Contract', 0777, true);
    file_put_contents($root.'/src/Contract/DemoContract.php', <<<'PHPFILE'
<?php
namespace App\Contract;
interface DemoContract { public function run(): void; }
PHPFILE);
    file_put_contents($root.'/src/Service/Example/DemoService.php', <<<'PHPFILE'
<?php
namespace App\Service\Example;
use App\Contract\DemoContract;
final class DemoService implements DemoContract { public function run(): void {} }
PHPFILE);
    $mirror = (new ServiceInterfaceMirrorRule())->check(new RuleContext($root, ['component' => ['namespace' => 'App']]));
    $assert('skipped' === $mirror->status, 'A service without a mirrored ServiceInterface must not fail.');

    mkdir($root.'/src/ServiceInterface/Example', 0777, true);
    file_put_contents($root.'/src/ServiceInterface/Example/DemoServiceInterface.php', <<<'PHPFILE'
<?php
namespace App\ServiceInterface\Example;
interface DemoServiceInterface { public function run(): void; }
PHPFILE);
    file_put_contents($root.'/src/Service/Example/DemoService.php', <<<'PHPFILE'
<?php
namespace App\Service\Example;
use App\ServiceInterface\Example\DemoServiceInterface;
final class DemoService implements DemoServiceInterface { public function run(): void {} }
PHPFILE);
    $mirror = (new ServiceInterfaceMirrorRule())->check(new RuleContext($root, ['component' => ['namespace' => 'App']]));
    $assert('passed' === $mirror->status, 'Valid mirrored service must pass.');

    file_put_contents($root.'/src/Service/Example/DemoService.php', "<?php namespace App\\Service\\Example; final class DemoService { public function run(): void {} }\n");
    $mirror = (new ServiceInterfaceMirrorRule())->check(new RuleContext($root, ['component' => ['namespace' => 'App']]));
    $assert('failed' === $mirror->status, 'Service not implementing an existing mirror interface must fail.');

    unlink($root.'/src/ServiceInterface/Example/DemoServiceInterface.php');
    file_put_contents($root.'/src/ServiceInterface/Example/DemoContract.php', <<<'PHPFILE'
<?php
namespace App\ServiceInterface\Example;
interface DemoContract { public function run(): void; }
PHPFILE);
    $mirror = (new ServiceInterfaceMirrorRule())->check(new RuleContext($root, ['component' => ['namespace' => 'App']]));
    $assert('failed' === $mirror->status, 'Interfaces under ServiceInterface must use mirrored ServiceInterface naming.');

    $generatedReference = new Canon037GeneratedReferenceArtifactRule();
    $generatedReferenceRoot = $root.'/generated-reference';
    mkdir($generatedReferenceRoot.'/config', 0777, true);

    $generatedReferenceResult = $generatedReference->check(new RuleContext($generatedReferenceRoot));
    $assert('passed' === $generatedReferenceResult->status, 'Absent config/reference.php must pass Canon037.');

    $nonGitReferenceRoot = $root.'/non-git-reference';
    mkdir($nonGitReferenceRoot.'/config', 0777, true);
    file_put_contents($nonGitReferenceRoot.'/.git', "gitdir: missing-git-directory\n");
    file_put_contents($nonGitReferenceRoot.'/config/reference.php', "<?php return [];\n");
    $generatedReferenceResult = $generatedReference->check(new RuleContext($nonGitReferenceRoot));
    $assert('warning' === $generatedReferenceResult->status, 'Non-Git directory with config/reference.php must warn under Canon037; got '.$generatedReferenceResult->status.'.');
    unlink($nonGitReferenceRoot.'/config/reference.php');

    $assert(0 === $runCommand(['git', '-C', $generatedReferenceRoot, 'init', '--quiet']), 'Canon037 calibration Git repository must initialize.');
    file_put_contents($generatedReferenceRoot.'/config/reference.php', "<?php return [];\n");
    $generatedReferenceResult = $generatedReference->check(new RuleContext($generatedReferenceRoot));
    $assert('passed' === $generatedReferenceResult->status, 'Untracked config/reference.php must pass Canon037.');

    file_put_contents($generatedReferenceRoot.'/config/other-generated.php', "<?php return [];\n");
    $assert(0 === $runCommand(['git', '-C', $generatedReferenceRoot, 'add', '-N', '--', 'config/other-generated.php']), 'Unrelated generated calibration file must be trackable.');
    $generatedReferenceResult = $generatedReference->check(new RuleContext($generatedReferenceRoot));
    $assert('passed' === $generatedReferenceResult->status, 'Tracked unrelated generated file must not trigger Canon037.');

    $assert(0 === $runCommand(['git', '-C', $generatedReferenceRoot, 'add', '-N', '--', 'config/reference.php']), 'Canon037 calibration artifact must be trackable.');
    $generatedReferenceResult = $generatedReference->check(new RuleContext($generatedReferenceRoot));
    $assert('failed' === $generatedReferenceResult->status, 'Tracked config/reference.php must fail Canon037.');
    unlink($generatedReferenceRoot.'/config/reference.php');
    unlink($generatedReferenceRoot.'/config/other-generated.php');

    $componentDocs = $root.'/component-docs';
    mkdir($componentDocs.'/docs/modules/ROOT/pages', 0777, true);
    file_put_contents($componentDocs.'/docs/antora.yml', "name: component-docs\nversion: true\n");
    $documentationOwnership = (new Canon036DocumentationProducerOwnershipRule())->check(new RuleContext($componentDocs));
    $assert('passed' === $documentationOwnership->status, 'docs/antora.yml producer surface must pass for an ordinary component.');

    file_put_contents($componentDocs.'/antora-playbook.yml', "site:\n  title: Component Portal\n");
    $documentationOwnership = (new Canon036DocumentationProducerOwnershipRule())->check(new RuleContext($componentDocs));
    $assert('failed' === $documentationOwnership->status, 'Root Antora playbook must fail for an ordinary component.');
    unlink($componentDocs.'/antora-playbook.yml');

    file_put_contents($componentDocs.'/antora.yml', "name: misplaced\nversion: true\n");
    $documentationOwnership = (new Canon036DocumentationProducerOwnershipRule())->check(new RuleContext($componentDocs));
    $assert('failed' === $documentationOwnership->status, 'Root antora.yml must fail for an ordinary component producer.');

    $documentating = $root.'/Documentating';
    mkdir($documentating, 0777, true);
    file_put_contents($documentating.'/antora-playbook.yml', "site:\n  title: SmartResponsor\n");
    $documentationOwnership = (new Canon036DocumentationProducerOwnershipRule())->check(new RuleContext($documentating));
    $assert('passed' === $documentationOwnership->status, 'Documentating must be explicitly exempt from the component site-owner prohibition.');

    mkdir($root.'/vendor/package', 0777, true);
    mkdir($root.'/var/cache', 0777, true);
    mkdir($root.'/src/Command', 0777, true);
    file_put_contents($root.'/vendor/package/VendorClass.php', '<?php class VendorClass {}');
    file_put_contents($root.'/var/cache/CachedClass.php', '<?php class CachedClass {}');
    file_put_contents($root.'/src/Command/DemoCommand.php', '<?php namespace App\Command; final class DemoCommand {}');
    $inventory = (new InventoryScanner())->scan($root);
    $assert(5 === $inventory['php_files'], 'Inventory must exclude vendor and var while keeping application-owned PHP files.');
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @chmod($item->getPathname(), 0777);
            @rmdir($item->getPathname());
            continue;
        }

        @chmod($item->getPathname(), 0666);
        @unlink($item->getPathname());
    }
    @rmdir($root);
}

if ([] !== $failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

echo "Calibration tests passed.\n";
