# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
param(
  [string]$Path = ".",
  [string]$Report = "report/layer-mirror-check.json",
  [switch]$NoWrite
)
$ErrorActionPreference = "Stop"
$root = Resolve-Path -Path $Path
$arg = @("--path", $root, "--report", $Report)
if ($NoWrite) { $arg += "--no-write" }
$gateDir = Join-Path $root ".gating"
if (-not (Test-Path -LiteralPath $gateDir)) {
  $gateDir = $root
}
node (Join-Path $gateDir "linting/js/layer-mirror-check.js") @arg
exit $LASTEXITCODE
