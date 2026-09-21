# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
param(
  [Parameter(Mandatory=$false)][string]$RepoRoot = (Get-Location).Path
)

$ErrorActionPreference = "Stop"

& (Join-Path $RepoRoot ".gating/quality/owner/ps1/phpstan-run.ps1") -RepoRoot $RepoRoot
& (Join-Path $RepoRoot ".gating/quality/owner/ps1/rector-run.ps1") -RepoRoot $RepoRoot
