<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Registry;

use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Rule\Canon\Canon000ComponentPrefixRule;
use Gating\Gate\Rule\Canon\Canon001TechnicalRoleFirstRule;
use Gating\Gate\Rule\Canon\Canon002InterfaceTreeMirrorsImplementationRule;
use Gating\Gate\Rule\Canon\Canon003DtoIsExplicitRule;
use Gating\Gate\Rule\Canon\Canon004SubjectFolderPlacementRule;
use Gating\Gate\Rule\Canon\Canon005MeaningfulNamespaceTokensRule;
use Gating\Gate\Rule\Canon\Canon006OneDominantTechnicalRoleRule;
use Gating\Gate\Rule\Canon\Canon007Psr4IdentityRule;
use Gating\Gate\Rule\Canon\Canon008ComposerDependencyIntegrityRule;
use Gating\Gate\Rule\Canon\Canon009ComponentHostBoundaryRule;
use Gating\Gate\Rule\Canon\Canon010ArchitectureMigrationCompletenessRule;
use Gating\Gate\Rule\Canon\Canon011NoSilentFailureRule;
use Gating\Gate\Rule\Canon\Canon012TypedBoundaryContractRule;
use Gating\Gate\Rule\Canon\Canon013NoPlaceholderProductionLogicRule;
use Gating\Gate\Rule\Canon\Canon014ExecutableResponsibilityRule;
use Gating\Gate\Rule\Canon\Canon015NoToolingArchitectureLeakRule;
use Gating\Gate\Rule\Canon\Canon016ExplicitCompatibilityLifecycleRule;
use Gating\Gate\Rule\Canon\Canon017DocumentationMatchesRuntimeRule;
use Gating\Gate\Rule\Canon\Canon018ComposerIdentityMappingRule;
use Gating\Gate\Rule\Canon\Canon019NoAlternativeLayerTaxonomyRule;
use Gating\Gate\Rule\Canon\Canon020TypedSymfonyRoleRootRule;
use Gating\Gate\Rule\Canon\Canon021CrudingOwnsGenericCrudRule;
use Gating\Gate\Rule\Canon\Canon022StandaloneApplicationDependencyBaselineRule;
use Gating\Gate\Rule\Canon\Canon023DevelopmentComposerSymlinkRule;
use Gating\Gate\Rule\Canon\Canon024ProductionComposerBundleRule;
use Gating\Gate\Rule\Canon\Canon025ComponentDualRuntimeModeRule;
use Gating\Gate\Rule\Canon\Canon026PlatformVersionBaselineRule;
use Gating\Gate\Rule\Canon\Canon027DatabaseEngineBaselineRule;
use Gating\Gate\Rule\Canon\Canon028DualDoctrineConnectionRule;
use Gating\Gate\Rule\Canon\Canon029MandatoryPhpQualityToolingRule;
use Gating\Gate\Rule\Canon\Canon030DoctrineSchemaParityRule;
use Gating\Gate\Rule\Canon\Canon031PhpDocCoverageRule;
use Gating\Gate\Rule\Canon\Canon032BundleRegistrationRule;
use Gating\Gate\Rule\Canon\Canon033ComposerManifestIdentityParityRule;
use Gating\Gate\Rule\Canon\Canon034GitignoreBaselineRule;
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
use Gating\Gate\Rule\Composer\ComposerPlatformRule;
use Gating\Gate\Rule\Database\DatabaseTablePrefixRule;
use Gating\Gate\Rule\Documentation\DocblockPreservationRule;
use Gating\Gate\Rule\Inventory\ComponentInventoryRule;
use Gating\Gate\Rule\Layer\TypedLayerRule;
use Gating\Gate\Rule\Mirror\ServiceInterfaceMirrorRule;
use Gating\Gate\Rule\Mutation\MutationSafetyRule;
use Gating\Gate\Rule\Namespace\NamespaceProfileRule;
use Gating\Gate\Rule\Profile\ProfileContractRule;
use Gating\Gate\Rule\Release\ReleaseEvidenceRule;
use Gating\Gate\Rule\Route\RouteOwnerRootRule;
use Gating\Gate\Rule\Route\RoutePathSegmentSeparationRule;
use Gating\Gate\Rule\Security\SecretLeakRule;
use Gating\Gate\Rule\Structure\ForbiddenArchitectureRule;

/**
 * Provides the rule registry implementation used by the Gating runtime and rule execution flow.
 */
final readonly class RuleRegistry
{
    /** @return list<RuleInterface> */
    public function all(): array
    {
        return [
            new Canon000ComponentPrefixRule(),
            new Canon001TechnicalRoleFirstRule(),
            new Canon002InterfaceTreeMirrorsImplementationRule(),
            new Canon003DtoIsExplicitRule(),
            new Canon004SubjectFolderPlacementRule(),
            new Canon005MeaningfulNamespaceTokensRule(),
            new Canon006OneDominantTechnicalRoleRule(),
            new Canon007Psr4IdentityRule(),
            new Canon008ComposerDependencyIntegrityRule(),
            new Canon009ComponentHostBoundaryRule(),
            new Canon010ArchitectureMigrationCompletenessRule(),
            new Canon011NoSilentFailureRule(),
            new Canon012TypedBoundaryContractRule(),
            new Canon013NoPlaceholderProductionLogicRule(),
            new Canon014ExecutableResponsibilityRule(),
            new Canon015NoToolingArchitectureLeakRule(),
            new Canon016ExplicitCompatibilityLifecycleRule(),
            new Canon017DocumentationMatchesRuntimeRule(),
            new Canon018ComposerIdentityMappingRule(),
            new Canon019NoAlternativeLayerTaxonomyRule(),
            new Canon020TypedSymfonyRoleRootRule(),
            new Canon021CrudingOwnsGenericCrudRule(),
            new Canon022StandaloneApplicationDependencyBaselineRule(),
            new Canon023DevelopmentComposerSymlinkRule(),
            new Canon024ProductionComposerBundleRule(),
            new Canon025ComponentDualRuntimeModeRule(),
            new Canon026PlatformVersionBaselineRule(),
            new Canon027DatabaseEngineBaselineRule(),
            new Canon028DualDoctrineConnectionRule(),
            new Canon029MandatoryPhpQualityToolingRule(),
            new Canon030DoctrineSchemaParityRule(),
            new Canon031PhpDocCoverageRule(),
            new Canon032BundleRegistrationRule(),
            new Canon033ComposerManifestIdentityParityRule(),
            new Canon034GitignoreBaselineRule(),
            new Canon035SymfonyContainerReuseRule(),
            new Canon036DocumentationProducerOwnershipRule(),
            new Canon037GeneratedReferenceArtifactRule(),
            new Canon038ConfigYamlSubjectPrefixRule(),
            new Canon039PhpTestToolingRule(),
            new Canon040PhpTestCoverageRule(),
            new Canon041BehavioralUiTestToolingRule(),
            new Canon042BehavioralUiCoverageRule(),
            new Canon043DevelopmentComposerDependencyVersionRule(),
            new Canon044ObjectingSystemFieldNamingRule(),
            new Canon045DevelopmentComposerRepositoryClosureRule(),
            new CanonRuleMirrorRule(),
            new ProfileContractRule(),
            new ForbiddenArchitectureRule(),
            new NamespaceProfileRule(),
            new TypedLayerRule(),
            new ServiceInterfaceMirrorRule(),
            new ComposerPlatformRule(),
            new DatabaseTablePrefixRule(),
            new RouteOwnerRootRule(),
            new RoutePathSegmentSeparationRule(),
            new MutationSafetyRule(),
            new SecretLeakRule(),
            new ReleaseEvidenceRule(),
            new DocblockPreservationRule(),
            new ComponentInventoryRule(),
        ];
    }

    /**
     * @param list<string> $enabledRuleIds
     *
     * @return list<RuleInterface>
     */
    public function only(array $enabledRuleIds): array
    {
        if ([] === $enabledRuleIds) {
            return $this->all();
        }

        $expandedRuleIds = [];
        foreach ($enabledRuleIds as $ruleId) {
            if ('route.owner_token' === $ruleId) {
                $expandedRuleIds[] = 'route.owner_root';
                $expandedRuleIds[] = 'route.path_segment_separation';
                continue;
            }
            $expandedRuleIds[] = $ruleId;
        }

        $enabled = array_fill_keys($expandedRuleIds, true);
        $rules = [];
        foreach ($this->all() as $rule) {
            if (isset($enabled[$rule->id()])) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /** @return list<array{id:string,area:string,scope:string,summary:string,kind:string}> */
    public function catalog(): array
    {
        return [
            ['id' => 'canon.000.component_prefix', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Enforces Canon000 component PHP subject prefixes.'],
            ['id' => 'canon.001.technical_role_first', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Enforces Canon001 technical-role-first source topology.'],
            ['id' => 'canon.002.interface_tree_mirror', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Enforces Canon002 interface/implementation tree mirroring.'],
            ['id' => 'canon.003.dto_explicit', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Enforces Canon003 explicit DTO naming and placement.'],
            ['id' => 'canon.004.subject_folder_placement', 'area' => 'canon', 'scope' => 'profile', 'kind' => 'canon-linked', 'summary' => 'Enforces Canon004 subject folder placement with Entity exception.'],
            ['id' => 'canon.005.meaningful_namespace_tokens', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Detects Canon005 known meaningless namespace-token patterns.'],
            ['id' => 'canon.006.dominant_technical_role', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Enforces Canon006 dominant technical role naming/location.'],
            ['id' => 'canon.007.psr4_identity', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Enforces Canon007 PSR-4 path/namespace/declaration identity.'],
            ['id' => 'canon.008.composer_dependency_integrity', 'area' => 'canon', 'scope' => 'profile', 'kind' => 'canon-linked', 'summary' => 'Enforces Canon008 foreign namespace to Composer dependency integrity.'],
            ['id' => 'canon.009.component_host_boundary', 'area' => 'canon', 'scope' => 'profile', 'kind' => 'canon-linked', 'summary' => 'Enforces Canon009 standalone component versus Host implementation boundary.'],
            ['id' => 'canon.010.migration_completeness', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Checks Canon010 stale architecture references after migration.'],
            ['id' => 'canon.011.no_silent_failure', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Enforces observable mandatory failures and flags silent fallback patterns.'],
            ['id' => 'canon.012.typed_boundary_contract', 'area' => 'canon', 'scope' => 'profile', 'kind' => 'canon-linked', 'summary' => 'Reviews dynamic internal contracts in configured role roots.'],
            ['id' => 'canon.013.no_placeholder_production_logic', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Blocks known production placeholder logic and reviews TODO/FIXME markers.'],
            ['id' => 'canon.014.executable_responsibility', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Reviews oversized executable orchestration objects for responsibility accumulation.'],
            ['id' => 'canon.015.no_tooling_architecture_leak', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Detects namespaced reusable PHP types under tooling roots.'],
            ['id' => 'canon.016.explicit_compatibility_lifecycle', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Reviews compatibility surfaces for explicit lifecycle metadata.'],
            ['id' => 'canon.017.documentation_matches_runtime', 'area' => 'canon', 'scope' => 'profile', 'kind' => 'canon-linked', 'summary' => 'Checks authoritative documentation for configured stale runtime/API tokens.'],
            ['id' => 'canon.018.composer_identity_mapping', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Derives component namespace and PHP subject vocabulary from Composer package identity.'],
            ['id' => 'canon.019.no_alternative_layer_taxonomy', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Blocks competing Domain/Application/Infrastructure and Port/Adapter architecture roots.'],
            ['id' => 'canon.020.typed_symfony_role_root', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Blocks generic source roots that hide explicit Symfony/application technical roles.'],
            ['id' => 'canon.021.cruding_owns_generic_crud', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Reviews component-local generic CRUD machinery while exempting EasyAdmin back-office CRUD.'],
            ['id' => 'canon.022.standalone_application_dependency_baseline', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires Cruding, Collectioning, Tabling, Viewing, Interfacing, Objecting, and EasyAdmin as direct runtime dependencies of standalone Symfony applications.'],
            ['id' => 'canon.023.development_composer_symlink', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires local sibling Composer path repositories to use symlink=true in development.'],
            ['id' => 'canon.024.production_composer_bundle', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires composer.prod.json and rejects path/symlink repositories in production.'],
            ['id' => 'canon.025.component_dual_runtime_mode', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires standalone Symfony boot surfaces and a reusable component bundle surface.'],
            ['id' => 'canon.026.platform_version_baseline', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires PHP 8.4+ and Symfony 8.1+ within the Symfony 8 major line.'],
            ['id' => 'canon.027.database_engine_baseline', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Restricts canonical relational engines to PostgreSQL and SQLite.'],
            ['id' => 'canon.028.dual_doctrine_connection', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Checks canonical Doctrine data/PostgreSQL and infra/SQLite connection roles.'],
            ['id' => 'canon.029.mandatory_php_quality_tooling', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires PHP-CS-Fixer and PHPStan as configured dev tooling exposed through Composer scripts.'],
            ['id' => 'canon.030.doctrine_schema_parity', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires Doctrine ORM/migrations repositories to expose executable schema-parity validation against current Entity metadata.'],
            ['id' => 'canon.031.phpdoc_coverage', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Warns when meaningful class or method PHPDoc coverage falls below 70%.'],
            ['id' => 'canon.032.bundle_registration', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires a reusable component bundle to be registered by standalone bundle configuration.'],
            ['id' => 'canon.033.composer_manifest_identity_parity', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Keeps development and production Composer package identity aligned without requiring identical dependency sets.'],
            ['id' => 'canon.034.gitignore_baseline', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Checks .gitignore coverage for canonical dependency, runtime, local, quality, IDE, and OS noise.'],
            ['id' => 'canon.035.symfony_container_reuse', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Blocks known request-time Symfony container invalidation and unstable cache-identity patterns.'],
            ['id' => 'canon.036.documentation_producer_ownership', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Enforces component documentation-producer topology while reserving Antora site ownership for Documentating.'],
            ['id' => 'canon.037.generated_reference_artifact', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Keeps reproducible generated config/reference.php outside repository source history.'],
            ['id' => 'canon.038.config_yaml_subject_prefix', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Namespaces component-owned YAML filenames with the Canon018 subject token while exempting framework/vendor bootstrap conventions.'],
            ['id' => 'canon.039.php_test_tooling', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires PHPUnit, explicit production source coverage, branch instrumentation, and a persistent standard text coverage summary.'],
            ['id' => 'canon.040.php_test_coverage', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Measures line, method, and branch coverage independently and classifies high test debt.'],
            ['id' => 'canon.041.behavioral_ui_test_tooling', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires Symfony Test Pack, Panther, and repository-local Playwright tooling for standalone Symfony applications.'],
            ['id' => 'canon.042.behavioral_ui_coverage', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Evaluates explicit functional, behavioral, UI, and critical-workflow coverage evidence and classifies behavioral test debt.'],
            ['id' => 'canon.043.development_composer_dependency_version', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires locally linked first-party Composer path dependencies to use exact dev-master constraints.'],
            ['id' => 'canon.044.objecting_system_field_naming', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires Objecting persisted system fields and Doctrine columns to use entity-native names without object/objecting prefixes.'],
            ['id' => 'canon.045.development_composer_repository_closure', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'canon-linked', 'summary' => 'Requires root development Composer manifests to expose the complete reachable local first-party path-repository closure.'],
            ['id' => 'canon.mirror_contract', 'area' => 'canon', 'scope' => 'platform', 'kind' => 'meta-canon', 'summary' => 'Validates CanonNNN mirror naming/coverage contract.'],
            ['id' => 'profile.contract_validity', 'area' => 'profile', 'scope' => 'profile', 'kind' => 'profile-canon', 'summary' => 'Ensures component profiles use the common Gating profile contract.'],
            ['id' => 'structure.forbidden_architecture', 'area' => 'structure', 'scope' => 'platform', 'kind' => 'hard-canon', 'summary' => 'Blocks /src/Domain, Port, Adapter, and Adaptor folders.'],
            ['id' => 'namespace.profile_match', 'area' => 'namespace', 'scope' => 'profile', 'kind' => 'profile-canon', 'summary' => 'Ensures PHP namespaces stay under the namespace declared by component profile.'],
            ['id' => 'layer.typed_class_location', 'area' => 'layer', 'scope' => 'platform', 'kind' => 'hard-canon', 'summary' => 'Ensures class suffixes live in matching typed Symfony folders.'],
            ['id' => 'mirror.service_interface', 'area' => 'mirror', 'scope' => 'profile', 'kind' => 'profile-canon', 'summary' => 'Ensures Service classes have mirrored ServiceInterface contracts when enabled.'],
            ['id' => 'composer.platform_constraint', 'area' => 'composer', 'scope' => 'platform', 'kind' => 'hard-canon', 'summary' => 'Checks PHP and Symfony package constraints against the ecosystem baseline.'],
            ['id' => 'database.table_prefix', 'area' => 'database', 'scope' => 'profile', 'kind' => 'profile-canon', 'summary' => 'Checks Doctrine table names against the database prefix declared by component profile.'],
            ['id' => 'route.owner_root', 'area' => 'route', 'scope' => 'profile', 'kind' => 'profile-canon', 'summary' => 'Ensures routes begin with an allowed stable owner root.'],
            ['id' => 'route.path_segment_separation', 'area' => 'route', 'scope' => 'platform', 'kind' => 'hard-canon', 'summary' => 'Ensures each route concept uses its own slash-separated path segment.'],
            ['id' => 'mutation.safety_firewall', 'area' => 'mutation', 'scope' => 'platform', 'kind' => 'safety-canon', 'summary' => 'Blocks broad destructive cleanup/apply/fix script patterns by default.'],
            ['id' => 'security.secret_leak', 'area' => 'security', 'scope' => 'platform', 'kind' => 'security-canon', 'summary' => 'Scans for obvious secret/token/private-key leaks with redacted evidence.'],
            ['id' => 'release.evidence_manifest', 'area' => 'release', 'scope' => 'profile', 'kind' => 'profile-canon', 'summary' => 'Checks required release/evidence files when release evidence is enabled by profile.'],
            ['id' => 'documentation.docblock_preservation', 'area' => 'documentation', 'scope' => 'platform', 'kind' => 'hard-canon', 'summary' => 'Guards descriptive docblocks from risky formatter configuration.'],
            ['id' => 'inventory.component_surface', 'area' => 'inventory', 'scope' => 'platform', 'kind' => 'reporting-canon', 'summary' => 'Produces a component surface inventory for reports and follow-up gates.'],
        ];
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_map(static fn (array $item): string => $item['id'], $this->catalog());
    }
}
