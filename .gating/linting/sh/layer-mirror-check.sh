#!/usr/bin/env bash
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
set -euo pipefail
ROOT="${1:-.}"
ROOT="$(cd "$ROOT" && pwd)"
GATE_DIR="$ROOT/.gating"
if [[ ! -d "$GATE_DIR" ]]; then
  GATE_DIR="$ROOT"
fi
node "$GATE_DIR/linting/js/layer-mirror-check.js" --path "$ROOT"
