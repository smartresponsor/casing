# CMCP Execution Journal

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


