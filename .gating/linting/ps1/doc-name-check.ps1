# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
param(
  [string]$Root = ".",
  [string]$Domain = "canon",
  [string]$Dir = ""
)
$ErrorActionPreference = "Stop"
$repoRoot = Resolve-Path -Path $Root
$gateDir = Join-Path $repoRoot ".gating"
if (-not (Test-Path -LiteralPath $gateDir)) {
  $gateDir = $repoRoot
}

$cmd = @("--root", $repoRoot, "--domain", $Domain)
if ($Dir -ne "") { $cmd += @("--dir", $Dir) }

node (Join-Path $gateDir "linting/js/doc-name-check.js") @cmd
exit $LASTEXITCODE
