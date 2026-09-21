# Gating runtime source

This folder contains the Symfony-oriented PHP runtime for the executable Gating component.

Repository policy/configuration lives in `.gating/`.
Symfony Console commands live under `src/Command/`.
Shared execution/services live under `src/Service/`.

Canonical identity:

- Composer package: `gating/gate`
- Symfony Console entrypoint: `bin/gating`

No Symfony web application surface is introduced here.
