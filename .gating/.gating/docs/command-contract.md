# Gating Command Contract

The CLI uses stable command names so local tools, Commanding, CI, and future Administering screens can call Gating consistently.

| Command | Meaning | Mutates files |
|---|---|---|
| `check` | Run selected read-only rules and print text output. | No |
| `scan` | Run selected read-only rules and print JSON output. | No |
| `report` | Alias for JSON-friendly scan/report output. | No |
| `inventory` | Alias for scan with inventory evidence. | No |
| `list-rules` | Print registered rule catalog. | No |

Required options are intentionally minimal:

```bash
--target=/path/to/repository
--profile=.gating/profile/component/name.yaml
--rule-set=.gating/profile/rule-set/strict.yaml
--report-file=.gating/report/result.json
```

Any future mutation command must be added under an explicit safety contract and must not be the default behavior.

## W10 commands

### discover-targets

```bash
php .gating/bin/gating discover-targets --root=. --max-depth=2 --json
```

Discovers candidate component/package roots. This command is read-only and returns `gating.target.discovery.v1`.

### baseline

```bash
php .gating/bin/gating baseline --root=. --max-depth=2 --rule-set=.gating/profile/rule-set/local-dev.yaml --report-file=.gating/report/baseline.json
```

Runs a selected rule set against discovered targets and writes `gating.baseline.v1`.

### self-check

```bash
php .gating/bin/gating self-check
```

Runs the local `.gating/` package against its own profile and local-dev rule set.

## W11 policy options

```bash
--severity-config=.gating/config/severity.yaml
```

The severity config is read-only policy input. It lets the same raw rule become blocking or report-only in different automation scenarios without changing rule code.

Stable exit codes are available through:

```bash
php .gating/bin/gating exit-codes
```

| Code | Meaning |
| ---: | --- |
| 0 | Passed. No error-severity failure was found. |
| 1 | Policy failed. One or more error-severity rules failed. |
| 2 | Usage/config error. |
| 3 | Internal runtime error, reserved. |
