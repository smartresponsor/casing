#!/usr/bin/env bash
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
set -euo pipefail

DOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

normalize_root() {
  local r="$1"
  # If caller passed the .gating folder as root (common when invoked from menu), use parent
  if [[ "$(basename -- "$r")" == ".gating" ]]; then
    printf "%s" "$(cd -- "$r/.." && pwd)"
    return 0
  fi
  printf "%s" "$r"
}

resolve_root() {
  local d="$DOT_DIR"
  local i
  for i in $(seq 1 50); do
    # repo root marker: has .gating/gate.sh
    if [ -f "$d/.gating/gate.sh" ]; then
      printf "%s" "$d"
      return 0
    fi

    local p
    p="$(cd -- "$d/.." && pwd)"
    [ "$p" != "$d" ] || break
    d="$p"
  done
  printf "%s" "$(cd -- "$DOT_DIR/.." && pwd)"
}

ROOT_RAW="${REPO_ROOT:-"$(resolve_root)"}"
ROOT="$(normalize_root "$ROOT_RAW")"

GATE="$ROOT/.gating/gate.sh"
if [ ! -f "$GATE" ]; then
  # compat: allow run.sh to live in the same folder as gate.sh
  GATE="$DOT_DIR/gate.sh"
fi

if [ ! -f "$GATE" ]; then
  echo "[run] missing gate.sh (checked: $ROOT/.gating/gate.sh and $DOT_DIR/gate.sh)"
  exit 2
fi

cd "$ROOT"
bash "$GATE" "$ROOT"
