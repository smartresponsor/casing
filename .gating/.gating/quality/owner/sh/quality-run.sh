#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="${1:-$(pwd)}"
# expects vendor/bin/phpstan and vendor/bin/rector
vendor/bin/phpstan analyse -c "$REPO_ROOT/.gating/quality/owner/php/phpstan.neon"
vendor/bin/rector process --dry-run --config "$REPO_ROOT/.gating/quality/owner/php/rector.php"
