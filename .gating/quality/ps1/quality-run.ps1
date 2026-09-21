param(
  [Parameter(Mandatory=$false)][string]$RepoRoot = (Get-Location).Path
)

$ErrorActionPreference = "Stop"

$gateDir = Join-Path $RepoRoot ".gating"
if (-not (Test-Path -LiteralPath $gateDir)) {
  $gateDir = $RepoRoot
}

& (Join-Path $gateDir "quality/ps1/phpstan-run.ps1") -RepoRoot $RepoRoot
& (Join-Path $gateDir "quality/ps1/rector-run.ps1") -RepoRoot $RepoRoot
