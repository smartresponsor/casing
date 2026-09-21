#!/usr/bin/env bash
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
set -euo pipefail

REPO_ROOT="${1:-$(pwd)}"
QUALITY="${QUALITY:-0}"
MODE="${MODE:-owner}" # owner | industrial | both

# Proposal path (relative to repo root)
PROPOSAL_PATH="${GATE_PROPOSAL_FILE:-.report/gate-fix-proposal.ndjson}"

is_ci() {
  [[ "${GITHUB_ACTIONS:-}" == "true" ]] && return 0
  [[ "${CI:-}" == "true" ]] && return 0
  return 1
}

AUTO_FIX_SAFE="${AUTO_FIX_SAFE:-}"
if [[ -z "$AUTO_FIX_SAFE" ]]; then
  if is_ci; then AUTO_FIX_SAFE="0"; else AUTO_FIX_SAFE="1"; fi
fi

# Normalize root + run from repo root
REPO_ROOT="$(cd "$REPO_ROOT" && pwd)"
cd "$REPO_ROOT"

REPO_TYPE="consumer"
if [[ "${GITHUB_REPOSITORY:-}" == */canonization ]]; then
  REPO_TYPE="canon"
fi

fail_step=""
fail_code=0

run_step() {
  local name="$1"; shift
  if [[ -n "$fail_step" ]]; then
    return 0
  fi

  set +e
  "$@"
  local code=$?
  set -e

  if [[ "$code" != "0" ]]; then
    fail_step="$name"
    fail_code="$code"
  fi
  return 0
}

print_proposal() {
  if [[ -f "$PROPOSAL_PATH" ]]; then
    echo "[gate] proposal file: $PROPOSAL_PATH"
    echo "[gate] proposal entries:"
    cat "$PROPOSAL_PATH" || true
  fi
}

apply_safe_if_local() {
  # only on local runs
  [[ "$AUTO_FIX_SAFE" == "1" ]] || return 0
  # only if failed
  [[ -n "$fail_step" ]] || return 0
  # avoid recursion
  [[ "${_GATE_AUTOFIX_RERUN:-0}" != "1" ]] || return 0
  # only if proposal exists
  [[ -f "$PROPOSAL_PATH" ]] || return 0

  echo "[gate] local autofix-safe enabled: applying proposals..."
  chmod +x ".gating/contract/fix.sh" 2>/dev/null || true
  ".gating/contract/fix.sh" "." "$PROPOSAL_PATH" safe || true

  echo "[gate] re-running gate after autofix-safe..."
  AUTO_FIX_SAFE="0" _GATE_AUTOFIX_RERUN="1" bash ".gating/gate.sh" "."
  exit $?
}

echo "[gate] repo=${GITHUB_REPOSITORY:-local} mode=${GATE_MODE:-unknown} root=$REPO_ROOT"

# Optional check index (policy-driven ordering)
CHECK_INDEX="${CHECK_INDEX:-}"
if [[ -z "$CHECK_INDEX" && -f "$REPO_ROOT/.gating/policy/owner/checks.index.json" ]]; then
  CHECK_INDEX="$REPO_ROOT/.gating/policy/owner/checks.index.json"
fi

run_indexed_checks() {
  local index_file="$1"
  if ! command -v node >/dev/null 2>&1; then
    echo "WARN: node not found; falling back to built-in step list." >&2
    return 1
  fi

  node - <<'NODE' "$index_file" "$REPO_ROOT"
const fs = require('fs');

const [,, indexFile] = process.argv;
const raw = fs.readFileSync(indexFile, 'utf8');
const data = JSON.parse(raw);
const items = Array.isArray(data.steps) ? data.steps : (Array.isArray(data.checks) ? data.checks : []);

for (const item of items) {
  if (!item || typeof item.id !== 'string') continue;
  const sh = item.sh || item;
  if (!sh || typeof sh.path !== 'string') continue;
  const runner = (sh.runner || item.runner || 'bash').toString();
  process.stdout.write(`${item.id}	${runner}	${sh.path}
`);
}
NODE
}

# Contract
if [[ -n "${CHECK_INDEX:-}" && -f "${CHECK_INDEX:-}" ]]; then
  while IFS=$'\t' read -r check_id runner check_path; do
    [[ -z "$check_id" || -z "$check_path" ]] && continue
    if [[ "$runner" == "bash" || "$runner" == "sh" ]]; then
      run_step "$check_id" bash "$check_path" "$REPO_ROOT"
    elif [[ "$runner" == "node" ]]; then
      if command -v node >/dev/null 2>&1; then
        run_step "$check_id" node "$check_path" "$REPO_ROOT"
      else
        echo "WARN: node not found for check=$check_id; skipping." >&2
      fi
    else
      echo "WARN: unsupported runner=$runner for check=$check_id; skipping." >&2
    fi
  done < <(run_indexed_checks "$CHECK_INDEX" || true)

  # If index failed to run (node missing / parse error), fall back.
  if [[ "${GATE_FAIL_FAST_ON_INDEX:-0}" == "1" ]]; then
    : # keep going; fail-fast handled by run_step
  fi
else
  run_step "root-contract-check" bash ".gating/contract/owner/root/sh/root-contract-check.sh" "$REPO_ROOT"
run_step "gitignore-template-check" bash ".gating/contract/owner/template/sh/gitignore-template-check.sh" "$REPO_ROOT"
fi

# Linting (fast checks) - JS checks require node
if command -v node >/dev/null 2>&1; then
  run_step "no-plural-check" node ".gating/linting/owner/js/no-plural-check.js" "$REPO_ROOT"
  run_step "layer-mirror-check-js" node ".gating/linting/owner/js/layer-mirror-check.js" "$REPO_ROOT"
  run_step "doc-name-check-js" node ".gating/linting/owner/js/doc-name-check.js" "$REPO_ROOT"
  run_step "archive-name-check-js" node ".gating/linting/owner/js/archive-name-check.js" "$REPO_ROOT"
else
  echo "[gate] node not found, skipping JS linting checks"
fi

run_step "copyright-header-check" bash ".gating/linting/owner/sh/copyright-header-check.sh" "$REPO_ROOT"
run_step "layer-mirror-check-sh" bash ".gating/linting/owner/sh/layer-mirror-check.sh" "$REPO_ROOT"
run_step "doc-name-check-sh" bash ".gating/linting/owner/sh/doc-name-check.sh" "$REPO_ROOT"
run_step "archive-flat-root-check" bash ".gating/linting/owner/sh/archive-flat-root-check.sh" "$REPO_ROOT"

if [[ "$QUALITY" == "1" ]]; then
  run_step "quality-run" bash ".gating/quality/owner/sh/quality-run.sh" "$REPO_ROOT"
fi

if [[ -n "$fail_step" ]]; then
  echo "[gate] FAIL step=$fail_step code=$fail_code"
  print_proposal
  apply_safe_if_local
  exit "$fail_code"
fi

echo "Gate OK"
