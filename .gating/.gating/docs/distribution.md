# Gating Distribution Model

`.gating/` is designed to be distributed from a trusted source and copied into component repositories without coupling the gate to any one component.

Supported modes:

1. **Dot-folder mode** — `.gating/` lives inside a target repository.
2. **Sibling mode** — one `.gating/` folder scans sibling component repositories by explicit `--target`.
3. **Standalone package mode** — `gating/gate` is installed as a CLI package.
4. **Future bundle mode** — a Symfony bundle adapter may consume the same rule kernel and reports.

The executable contract must remain target-driven:

```bash
php .gating/bin/gating check --target=/path/to/component --profile=.gating/profile/component/name.yaml
```

The gate must not hardcode local ecosystem paths. Component-specific values belong in profiles.
