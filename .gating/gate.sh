#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="${1:-$(pwd)}"
QUALITY="${QUALITY:-0}"
ARCHIVE_ZIPS="${ARCHIVE_ZIPS:-}"

GATE_DIR="$REPO_ROOT/.gating"
if [[ ! -d "$GATE_DIR" ]]; then
  GATE_DIR="$REPO_ROOT"
fi

# Contract
bash "$GATE_DIR/contract/sh/root-contract-check.sh" "$REPO_ROOT"
bash "$GATE_DIR/contract/sh/gitignore-template-check.sh" "$REPO_ROOT"

# Linting (fast checks) - JS checks require node
if command -v node >/dev/null 2>&1; then
  node "$GATE_DIR/linting/js/no-plural-check.js" --path "$REPO_ROOT"
  node "$GATE_DIR/linting/js/layer-mirror-check.js" --path "$REPO_ROOT"
  node "$GATE_DIR/linting/js/doc-name-check.js" --root "$REPO_ROOT"
  if [[ -n "$ARCHIVE_ZIPS" ]]; then
    for zip in $ARCHIVE_ZIPS; do
      node "$GATE_DIR/linting/js/archive-name-check.js" "$zip"
    done
  else
    echo "ARCHIVE_ZIPS not set, skipping archive-name-check"
  fi
else
  echo "node not found, skipping JS linting checks"
fi

bash "$GATE_DIR/linting/sh/copyright-header-check.sh" "$REPO_ROOT"
bash "$GATE_DIR/linting/sh/layer-mirror-check.sh" "$REPO_ROOT"
bash "$GATE_DIR/linting/sh/doc-name-check.sh" "$REPO_ROOT"
if [[ -n "$ARCHIVE_ZIPS" ]]; then
  for zip in $ARCHIVE_ZIPS; do
    bash "$GATE_DIR/linting/sh/archive-flat-root-check.sh" "$zip"
  done
else
  echo "ARCHIVE_ZIPS not set, skipping archive-flat-root-check"
fi

if [[ "$QUALITY" == "1" ]]; then
  bash "$GATE_DIR/quality/sh/quality-run.sh" "$REPO_ROOT"
fi

echo "Gate OK"
