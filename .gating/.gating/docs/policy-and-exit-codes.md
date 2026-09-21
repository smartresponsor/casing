# Gating policy and exit codes

Gating separates raw rule execution from policy interpretation.

## Severity policy

Rules report raw statuses: `passed`, `failed`, or `skipped`.
The severity policy converts failed results into blocking or non-blocking outcomes.

Supported severity values:

- `error` — failed rule blocks the run.
- `warning` — failed rule is reported but does not block by default.
- `info` — report-only signal.

Default policy file:

```text
.gating/config/severity.yaml
```

Example:

```yaml
severity:
  default: error
  rules:
    inventory.component_surface: info
    release.evidence_manifest: warning
```

## Profile suppressions

Suppressions are narrow, profile-owned exceptions for known legacy or transitional evidence.
They are not global rule changes.

Example:

```yaml
suppressions:
  structure.forbidden_architecture:
    - legacy/old-component/src/Domain
```

A suppression only applies when every evidence line for that failed rule matches one of the declared substrings.
Use `*` only after explicit review.

## Stable exit codes

| Code | Meaning |
| ---: | --- |
| 0 | Passed. No error-severity rule failed. |
| 1 | Policy failed. One or more error-severity rules failed. |
| 2 | Usage/config error. Invalid command, missing target/profile, unreadable config. |
| 3 | Internal runtime error. Reserved for future guarded runtime failures. |

Warnings, info results, skipped rules, and suppressed findings do not return exit code `1` by default.
