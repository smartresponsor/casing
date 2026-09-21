# Canon Rule Contract

Canonization owns normative architecture rules. Gating owns executable
enforcement of those rules.

## Identity

The stable cross-repository foreign key is `CanonNNN`.

The normative document and its direct executable projection mirror the same
semantic name and keep `Rule` as the single terminal technical-role token:

```text
Canonization: Canon003DtoIsExplicitRule.md
Gating:       Canon003DtoIsExplicitRule.php
```

Canonical PHP class:

```text
Canon003DtoIsExplicitRule
```

## Multiple executable rules

When one canon requires several executable projections, specialization is placed
before the terminal role token:

```text
Canon010ArchitectureMigrationNamespaceRule.php
Canon010ArchitectureMigrationConfigRule.php
Canon010ArchitectureMigrationTestRule.php
```

The family is discoverable by `Canon010*Rule.php`.

Generic rules such as `ServiceInterfaceMirrorRule` may be reused internally but
do not acquire a `CanonNNN` identity until their declared enforcement coverage
matches the canon projection they claim to implement.

Executable canon-linked PHP rules live under `src/Rule/Canon/` and implement `RuleInterface` directly or through shared canon-rule infrastructure.

## Admission boundary

Gating does not turn commodity formatter/static-analysis rules into duplicate Canon identities. PHP-CS-Fixer/PHPCS-style formatting and ordinary PHPStan/Rector-analyzable defects remain industry-tooling concerns. A `CanonNNN...Rule` exists when executable enforcement represents a SmartResponsor-specific architecture, topology, ownership, lifecycle, or semantic invariant.
