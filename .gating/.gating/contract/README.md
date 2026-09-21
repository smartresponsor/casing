Contract checks validate repository invariants.

Scope:
- Root contract: only dot-folders in repo root + required files.
- .gitignore must match the consumer template requirements.

Responsibility scopes:
- owner/: owner canon expectations (SmartResponsor owner rules).
- industrial/: industrial baseline expectations (portable, cross-repo).

Folders (within each responsibility scope, e.g. owner/):

| Path | Purpose |
| --- | --- |
| contract/<scope>/root/* | Root shape (dot-folder-only, required files). |
| contract/<scope>/template/* | Templates (gitignore template, editorconfig later). |

This folder must NOT contain linting checks (naming/style). Those belong to .gating/linting/.


Structure:
- root/: checks for repository root shape (dot-folders, required root files)
- template/: checks for consumer templates (e.g., .gitignore template)
