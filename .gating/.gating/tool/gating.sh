#!/usr/bin/env sh
set -eu
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
GATING_ROOT=$(dirname "$SCRIPT_DIR")
php "$GATING_ROOT/bin/gating" "$@"
