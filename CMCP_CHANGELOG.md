# CMCP Execution Journal

## 2026-09-13 — Casing repository implementation

### Iteration 1 — reconnaissance and baseline

- Read current Casing documentation, Composer/PHPUnit/PHP-CS-Fixer configuration, lifecycle/outbox code, architecture/unit tests, service/route configuration, and the existing CMCP journal.
- Read current Objecting, Cruding, Viewing, Interfacing, Gating, and Canonization contracts, including normative Canon001, Canon019, Canon021, Canon022, Canon023, Canon024, Canon029, Canon039, and the guard matrix.
- Baseline: branch `feature/facting-case-lifecycle-20260823`; pre-existing untracked `.gating/` left untouched; `composer test` passed 72 tests / 602 assertions; `composer cs:check` passed; strict Composer validation is structurally valid but warns on existing internal `*@dev` constraints.
- Canon mapping: role-first `App\\Casing\\` tree aligns with Canon001/019; EasyAdmin `CaseCrudController` is permitted by Canon021; Canon022 standalone baseline is not applicable because Casing has no standalone Symfony boot surface; local path repositories satisfy Canon023; Canon024/029/039 expose missing production-manifest, PHPStan execution, and persistent branch-coverage contracts.
- RC-critical work: correct DTO service-discovery casing and materialize missing production/static-analysis/test-coverage contracts. Growth remains separate: SLA/assignment/playbooks/agent-workspace and AI-assisted case capabilities.

### Iteration 2 — material implementation

- Corrected Symfony service discovery from non-existent `src/Dto/` to canonical `src/DTO/`, preventing DTO classes from being discovered as services on case-sensitive filesystems.
- Added an architecture regression test for the DTO exclusion.
- Added repository-owned PHPStan configuration plus Composer `phpstan` script for Canon029.
- Added persistent path/branch coverage execution through `test:coverage` for Canon039.
- Added `composer.prod.json` without sibling path repositories for the Canon024 production package contract.

### Iteration 3 — verification and fix

- Initial PHPStan execution exposed two calls to removed EasyAdmin `AdminContext::getReferrer()` and an unsafe Symfony recursive form-error union dereference; both runtime-significant findings were fixed.
- Added an explicit `CaseEntity` EasyAdmin generic declaration and request-header based admin referrer fallback.
- PHPStan now passes at the repository-owned level-5 floor without a generated baseline; only narrow Doctrine hydration false positives are exempted and PHPDoc certainty is disabled for defensive runtime checks.
- `composer test`: passed 73 tests / 605 assertions; `composer cs:check`: passed; changed/untracked PHP syntax lint passed.
- Persistent coverage execution now succeeds after creating `var/coverage/` before PHPUnit writes the summary.
- Measured coverage is lines 33.47%, methods 28.89%, branches 68.29%; Canon040 therefore classifies Casing as HIGH_TEST_DEBT because lines and methods remain below 50%.

### Iteration 4 — debt closure and integration

- Completed the mandatory dependency-contract pass by reading available Objecting, Cruding, Viewing and Interfacing README/Composer/AGENTS/manifest material; Interfacing has no `MANIFEST.json` at the expected root.
- Current gates: PHPStan green, PHPUnit green, PHP-CS-Fixer green, persistent path/branch coverage execution green, syntax lint green.
- `composer validate --strict --check-lock` confirms the manifest/lock are structurally valid but exits 1 solely for ten pre-existing unbound internal `*@dev` constraints; no sibling version was guessed.
- Pre-existing untracked `.gating/` remains untouched and excluded from integration.
- Remaining RC-critical debt is explicit rather than hidden: raise line/method coverage out of Canon040 HIGH_TEST_DEBT and replace unbound internal development constraints when authoritative package versions/branches are selected.

## engine-20260906000504-casing-ccf194

### Iteration 1 — reconnaissance and baseline

- Read: authoritative execution specification, repository Git/workspace context, Casing `composer.json`, and available canonical contracts from Objecting, Cruding, Viewing, Interfacing, Gating, and Canonization.
- Baseline: branch `feature/facting-case-lifecycle-20260823`; clean worktree before this journal; PHP `^8.4`; Symfony `^8.1`.
- Dependency contour: Objecting, Cruding, Viewing, and Interfacing are declared Composer requirements and local path repositories with symlinks.
- Selected RC-critical work: add missing root `README.md` with factual component scope, dependency-boundary guidance, and repository-local verification commands.
- Growth workstream: defer broader API/DX maturity documentation and competitive capability expansion; it is not required for this bounded acceptance test.
- Risks: documentation must not invent public APIs or move generic CRUD, system-field, presentation, or shell responsibilities into Casing.
- Gates: Composer validation, project coding-style check, PHPUnit suite, and final Git diff/status inspection.

### Iteration 2 — material implementation

- Added the missing root `README.md`.
- Documented the verified bundle/package identity, owned case capabilities, helper-component boundaries, lifecycle/outbox behavior, and repository-local verification commands.
- Kept the change documentation-only; no runtime behavior, dependencies, routes, or persistence mappings changed.

### Iteration 3 — verification and continuation decision

- `composer cs:check`: passed; 0 of 64 PHP files require formatting changes.
- `composer validate --strict --check-lock`: manifest and lock are valid, but strict mode exits 1 because ten internal `*@dev` constraints are unbound.
- `composer test`: reached 51 passing test indicators, then stopped with an external composition fatal in Cataloging: `CatalogCatalogEntity` and Objecting's `ObjectIdentityEmbeddableTrait` declare incompatible `$id` properties.
- Resulting state: only `README.md` and this orchestration journal are changed; no commit or push was performed.
- Continuation decision: the bounded acceptance test is complete. Further RC work remains outside this change: repair the Cataloging/Objecting identity collision, define bounded internal package versions, and separately assess the existing Casing EasyAdmin CRUD controller against the zero-generic-CRUD boundary.

## engine-20260906000821-casing-38eae7

### Iteration 1 — reconnaissance and baseline

- Resumed the existing acceptance-test objective without replacing or discarding the uncommitted `README.md` and journal.
- Re-read the authoritative continuation specification, current Git/workspace state, Casing documentation and manifest, and the available contracts for Objecting, Cruding, Viewing, Interfacing, Gating, and Canonization.
- Baseline: branch `feature/facting-case-lifecycle-20260823`; only `README.md` and `CMCP_CHANGELOG.md` are untracked; commit and push remain forbidden.
- Dependency contour remains explicit: Objecting, Cruding, Viewing, and Interfacing are Composer requirements and sibling path repositories; Gating and Canonization are policy sources rather than invented runtime dependencies.
- RC-critical continuation: preserve the prior README implementation, document verified acceptance constraints, rerun repository gates, and isolate local failures from dependency-composition failures.
- Growth workstream: richer operational/API documentation and administrative UX remain post-RC; they do not change this acceptance-test objective.
- Risks: the existing EasyAdmin `CaseCrudController` conflicts with the zero-generic-CRUD boundary, while the current full test suite is expected to encounter the Cataloging/Objecting identity collision outside Casing ownership.
- Gates: Composer validation, coding-style check, PHPUnit, and final diff/status inspection.

### Iteration 2 — material implementation

- Preserved and extended the uncommitted root `README.md` created by the preceding execution of the same acceptance test.
- Added a factual acceptance-constraints section that separates Casing checks from sibling-package composition failures.
- Recorded the existing EasyAdmin `CaseCrudController` as a boundary exception pending migration to Cruding and prohibited expansion of local generic CRUD infrastructure.
- No runtime code, dependency constraint, route, migration, or sibling repository was changed.

### Iteration 3 — verification and continuation decision

- `composer cs:check`: passed; 0 of 64 PHP files require formatting changes on PHP 8.4.13.
- Changed-PHP lint: passed with no changed PHP files to inspect.
- `composer validate --strict --check-lock`: manifest and lock are valid; strict exit 1 remains due to ten existing internal `*@dev` constraints.
- `composer test`: again reached 51 passing indicators, then stopped with the confirmed external Cataloging/Objecting `$id` trait-composition fatal.
- Local assessment: the README change is internally consistent and does not conceal either known RC constraint.
- Continuation decision: the three-iteration acceptance-test continuation is complete within Casing. Runtime RC remains blocked by the Cataloging/Objecting identity collision; generic CRUD ownership requires a separately authorized cross-component migration.

### Iteration 2 continuation — identity failure repair

- Decision received: fix the reported failure and continue.
- Confirmed Objecting's current canonical `ObjectIdentityEmbeddableTrait` owns nullable generated integer `id` and `getId()`.
- Removed duplicate local primary-key declarations from the six Cataloging entities that compose that trait; retained compact `id()` compatibility accessors as aliases to `getId()` where present.
- Verification: all six changed Cataloging PHP files pass syntax lint; Cataloging object-identity tests pass (2 tests, 29 assertions); Casing tests pass (72 tests, 602 assertions); Casing coding-style check passes (64 files).
- Cataloging's full coding-style check still reports one unrelated pre-existing PHPDoc spacing issue in `CatalogContractFactory.php`; it was not mixed into the identity repair.
- The previously reported runtime blocker is resolved. Remaining Casing follow-up is the separately bounded migration of its EasyAdmin generic CRUD controller to Cruding ownership.

## engine-20260906001905-casing-7afb57

### Iteration 1 — reconnaissance and baseline

- Resumed the live acceptance task after its answer-capture wait without discarding or rewriting the existing uncommitted `README.md` and journal.
- Re-read the authoritative specification, current Git state, Casing manifest/documentation, and the available relevant contracts from Objecting, Cruding, Viewing, Interfacing, Gating, and Canonization.
- Baseline: branch `feature/facting-case-lifecycle-20260823`; only `README.md` and `CMCP_CHANGELOG.md` are untracked; commit, push, destructive operations, and sibling-repository mutations remain forbidden.
- Browser capture is bound to the current task/chat and reports `waiting_for_assistant`; no later assistant artifact exists to guard yet.
- RC-critical continuation: preserve the accepted README work, rerun deterministic repository gates against the current dependency composition, and record whether the known external Cataloging/Objecting collision remains.
- Growth workstream: richer API/DX documentation and administrative UX remain post-RC and do not expand this bounded acceptance task.
- Risks: full-suite acceptance may remain blocked outside Casing by the Cataloging/Objecting `$id` composition conflict; the existing EasyAdmin controller remains a documented cross-component ownership exception.
- Gates: Composer validation, coding-style check, PHPUnit suite, browser/guard capture, and final Git status/diff inspection.

### Iteration 2 — material implementation

- Preserved the previously implemented root `README.md` and its explicit acceptance constraints; no speculative runtime workaround was introduced in Casing.
- Re-executed the current dependency-composed acceptance path after the runtime wait.
- `composer validate`: passed with the same ten non-blocking warnings for internal `*@dev` constraints.
- PHPUnit: produced 51 passing indicators before the unchanged external Cataloging/Objecting `$id` trait-composition fatal.
- The repeated result isolates the remaining runtime blocker outside the authorized Casing mutation boundary.

### Iteration 3 — verification and continuation decision

- Final branch/status: `feature/facting-case-lifecycle-20260823`; only untracked `README.md` and `CMCP_CHANGELOG.md` remain.
- Tracked `git diff --stat` is empty because both preserved task artifacts are untracked; no hidden tracked source mutation was made.
- Browser capture for this Task ID is correctly bound and has no assistant artifact beyond the executing conversation, so no independent later answer can be semantically guarded.
- Verification verdict: Casing's bounded acceptance continuation is complete; Composer metadata remains valid, and the full suite is externally blocked at the same Cataloging/Objecting identity collision after 51 passing indicators.
- Continuation decision: do not patch Cataloging, Objecting, or Cruding from this Casing-only task. Resolve the identity collision in its owning repository and authorize the generic CRUD migration separately, then rerun Casing acceptance.
- No staging, commit, push, destructive operation, dependency update, or sibling-repository mutation was performed.

## engine-20260906000821-casing-38eae7 — iteration 3/3 continuation

- Scope guard applied: all operations in this iteration were confined to Casing; no sibling repository was read or modified.
- Starting state: branch `feature/facting-case-lifecycle-20260823`, clean worktree.
- `composer test`: passed, 72 tests and 602 assertions.
- `composer cs:check`: passed, 0 of 64 files require changes.
- Changed-PHP lint: passed; there were no changed PHP files.
- `composer validate --strict --check-lock`: manifest and lock are consistent; strict exit 1 remains solely because ten internal dependencies use unbound `*@dev` constraints.
- Continuation decision: the previously recorded Cataloging/Objecting runtime blocker is no longer present in the installed composition. The next bounded Casing action is release-hardening of internal dependency constraints; the EasyAdmin-to-Cruding migration remains a separate cross-component action.
- This journal update is intentionally left uncommitted. No staging, commit, push, destructive operation, or sibling-repository mutation was performed in this iteration.

## engine-20260911143157-casing-6d3ed4 — iteration 4 continuation

- Console MCP workspace access restored for `D:\PhpstormProjects\www\Casing`; the earlier runtime-path blocker is closed.
- Verification exposed a current Shipping contract drift: `ShipmentPlacementFormData` was renamed by Shipping Canon003 to `ShipmentPlacementFormDTO`.
- Updated only Casing integration code and tests to consume the current Shipping-owned DTO; no sibling repository was modified.
- Preserved and verified the pending Casing identity/schema changes: local duplicate Objecting IDs removed, `case_status` qualified, and the matching PostgreSQL migration style-normalized.
- `composer test`: passed, 72 tests and 602 assertions.
- `composer cs:check`: passed, 0 of 65 files require changes.
- Changed/untracked PHP lint: passed.
- `composer validate --strict --check-lock`: manifest and lock are consistent; strict exit 1 remains solely for ten pre-existing unbound internal `*@dev` constraints.
- `.gating/` remains an untracked local enforcement copy and is intentionally excluded from the product commit.

