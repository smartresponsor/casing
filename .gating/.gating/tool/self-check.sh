#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
GATING_DIR="$(cd -- "${SCRIPT_DIR}/.." && pwd)"
php "${GATING_DIR}/bin/gating" self-check "$@"
