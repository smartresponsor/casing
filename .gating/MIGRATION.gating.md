# Migration note: `.gate` to `.gating`

This wave establishes the canonical Gating naming:

- old dot-folder: `.gate/`
- new dot-folder: `.gating/`
- Composer package: `gating/gate`

Apply the touched archive as an overlay. After verifying `.gating/` works in the target repository, remove the old `.gating/` folder manually or in a narrow reviewed patch. Do not run repository-wide cleanup scripts.

Compatibility wrappers at the repository root are updated to call `.gating/`.
