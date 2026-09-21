#!/usr/bin/env bash
# Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp
set -euo pipefail

ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
exec php "$ROOT/bin/gating" check --target="$ROOT" "$@"
