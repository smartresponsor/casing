#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="${1:-$(pwd)}"
REPO_ROOT="$(cd "$REPO_ROOT" && pwd)"
GATE_DIR="$REPO_ROOT/.gating"
if [[ ! -d "$GATE_DIR" ]]; then
  GATE_DIR="$REPO_ROOT"
fi
PHPSTAN_CFG="$GATE_DIR/quality/php/phpstan.neon"
RECTOR_CFG="$GATE_DIR/quality/php/rector.php"
PHPSTAN_BIN="$REPO_ROOT/vendor/bin/phpstan"
RECTOR_BIN="$REPO_ROOT/vendor/bin/rector"

if [[ ! -f "$PHPSTAN_CFG" ]]; then
  echo "phpstan.neon not found: $PHPSTAN_CFG" >&2
  exit 2
fi
if [[ ! -f "$RECTOR_CFG" ]]; then
  echo "rector.php not found: $RECTOR_CFG" >&2
  exit 2
fi
if [[ ! -x "$PHPSTAN_BIN" ]]; then
  echo "phpstan binary not found. Run composer install." >&2
  exit 2
fi
if [[ ! -x "$RECTOR_BIN" ]]; then
  echo "rector binary not found. Run composer install." >&2
  exit 2
fi

"$PHPSTAN_BIN" analyse -c "$PHPSTAN_CFG"
"$RECTOR_BIN" process --config "$RECTOR_CFG"
