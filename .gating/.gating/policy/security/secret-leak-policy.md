# Secret leak policy

Gating scans repository files for obvious secret material before release or shared delivery.

The rule is intentionally conservative and redacts detected values in reports.

Default ignored folders: `.git/`, `vendor/`, `node_modules/`, `var/`, `cache/`, `report/`, `evidence/`.
