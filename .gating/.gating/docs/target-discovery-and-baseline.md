# Target discovery and baseline reports

Gating is distributed as `.gating/`, but it must not assume a fixed local workspace path.
The executable gate discovers candidate targets from an explicit root and produces read-only baseline reports.

## Target discovery

```bash
php .gating/bin/gating discover-targets --root=. --max-depth=2
```

Discovery is intentionally conservative. A directory is treated as a candidate target when it has at least one of:

- `composer.json`
- `src/`
- `.gating/`

Ignored directories include `.git`, `vendor`, `node_modules`, `var`, `cache`, `report`, and `evidence`.

JSON output uses schema:

```text
gating.target.discovery.v1
```

## Baseline report

```bash
php .gating/bin/gating baseline \
  --root=. \
  --max-depth=2 \
  --rule-set=.gating/profile/rule-set/local-dev.yaml \
  --report-file=.gating/report/baseline.json
```

Baseline mode is non-destructive. It does not modify target repositories. It discovers targets, chooses a profile candidate when available, runs the selected rule set, and writes a machine-readable summary.

JSON output uses schema:

```text
gating.baseline.v1
```

## Self-check

```bash
php .gating/bin/gating self-check
```

Self-check runs the local `.gating/` package against `profile/component/gating.yaml` and the `local-dev` rule set. By default it writes:

```text
.gating/report/gating-self-report-w10.json
```

## Responsibility boundary

Discovery does not replace Commanding, Federation, or Administering.

- Commanding may invoke discovery/baseline commands.
- Federation/Discovery may provide a richer external component registry later.
- Administering may read generated JSON reports.
- Gating remains the executable validation engine.
