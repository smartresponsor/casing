# Gating Consumer Surface

`.gating/` is the repository-specific configuration surface for the executable Gating component.

It is intentionally not the runtime. Runtime code now lives in `src/`, and the Symfony Console application lives in `bin/gating`.

## Boundary

- `policy/` describes what is accepted.
- `profile/` provides component-specific values.
- `schema/` defines machine-readable result/report/evidence contracts.
- `report/` stores generated output.
- `evidence/` stores generated evidence.
- `gate.sh` and `gate.ps1` preserve compatibility entrypoints for the repository.

## Safety

Mutation is report-only by default. Any apply/fix/cleanup mode must be explicit and must not perform broad repository cleanup.

## W05 to W11 legacy content

The historical rule catalog, baseline reports, and compatibility data remain here for consumers that still refer to `.gating/`, but execution now happens in `bin/gating` against `src/`.
