#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="${1:-$(pwd)}"
CONTRACT_JSON="$REPO_ROOT/.gating/contract/contract.json"
GITIGNORE="$REPO_ROOT/.gitignore"

if [[ ! -f "$CONTRACT_JSON" ]]; then
  echo "contract.json not found: $CONTRACT_JSON" >&2
  exit 2
fi
if [[ ! -f "$GITIGNORE" ]]; then
  echo ".gitignore not found: $GITIGNORE" >&2
  exit 2
fi

if command -v jq >/dev/null 2>&1; then
  mapfile -t must < <(jq -r '.gitignore_template.must_include_all[]' "$CONTRACT_JSON")
else
  must=(".idea/" "*.iml" "/vendor/" "/var/" ".phpunit.result.cache" ".DS_Store" "Thumbs.db" "node_modules/" "/.env.local" "/.env.*.local")
fi

content="$(cat "$GITIGNORE")"
missing=()

for m in "${must[@]}"; do
  grep -Fq "$m" <<<"$content" || missing+=("$m")
done

if [[ ${#missing[@]} -gt 0 ]]; then
  echo ""
  echo ".gitignore template FAILED:" >&2
  for x in "${missing[@]}"; do
    echo " - missing: $x" >&2
  done
  echo ""
  exit 3
fi

echo ".gitignore template OK"
