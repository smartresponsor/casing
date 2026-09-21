#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="${1:-$(pwd)}"
CONTRACT_JSON="$REPO_ROOT/.gating/contract/contract.json"

if [[ ! -f "$CONTRACT_JSON" ]]; then
  echo "contract.json not found: $CONTRACT_JSON" >&2
  exit 2
fi

# Minimal portable parser: rely on jq if available, otherwise skip dot-folder allowlist
if command -v jq >/dev/null 2>&1; then
  mapfile -t required < <(jq -r '.root_contract.required_root_files[]' "$CONTRACT_JSON")
  mapfile -t allowed_files < <(jq -r '.root_contract.allowed_root_files_exact[]' "$CONTRACT_JSON")
  mapfile -t allowed_dots < <(jq -r '.allowed_root_dot_folders[]' "$CONTRACT_JSON")
else
  required=(".gitignore" "MANIFEST.json" "README.md")
  allowed_files=(".gitignore" "MANIFEST.json" "README.md")
  allowed_dots=()
fi

bad=()

while IFS= read -r -d '' entry; do
  name="$(basename "$entry")"
  if [[ -d "$entry" ]]; then
    if [[ "${name:0:1}" != "." ]]; then
      bad+=("Non-dot folder in root: $name")
    else
      if [[ ${#allowed_dots[@]} -gt 0 ]]; then
        found=0
        for d in "${allowed_dots[@]}"; do
          [[ "$d" == "$name" ]] && found=1 && break
        done
        [[ $found -eq 0 ]] && bad+=("Unexpected dot-folder in root: $name")
      fi
    fi
  else
    found=0
    for f in "${allowed_files[@]}"; do
      [[ "$f" == "$name" ]] && found=1 && break
    done
    [[ $found -eq 0 ]] && bad+=("Unexpected root file: $name")
  fi
done < <(find "$REPO_ROOT" -maxdepth 1 -mindepth 1 -print0)

for rf in "${required[@]}"; do
  [[ -e "$REPO_ROOT/$rf" ]] || bad+=("Missing required root file: $rf")
done

if [[ ${#bad[@]} -gt 0 ]]; then
  echo ""
  echo "Root contract FAILED:" >&2
  for b in "${bad[@]}"; do
    echo " - $b" >&2
  done
  echo ""
  exit 2
fi

echo "Root contract OK"
