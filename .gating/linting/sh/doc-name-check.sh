#!/usr/bin/env bash
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
set -euo pipefail

ROOT="${1:-.}"
DOMAIN="${2:-canon}"
DIR="${3:-}"

ROOT="$(cd "$ROOT" && pwd)"
GATE_DIR="$ROOT/.gating"
if [[ ! -d "$GATE_DIR" ]]; then
  GATE_DIR="$ROOT"
fi

ARGS=(--root "$ROOT" --domain "$DOMAIN")
if [[ -n "$DIR" ]]; then
  ARGS+=(--dir "$DIR")
fi

node "$GATE_DIR/linting/js/doc-name-check.js" "${ARGS[@]}"
