param(
  [Parameter(Mandatory=$false)][string]$RepoRoot = (Get-Location).Path,
  [Parameter(Mandatory=$false)][switch]$Quality
)

$ErrorActionPreference = "Stop"
$ArchiveZips = $env:ARCHIVE_ZIPS

$gateDir = Join-Path $RepoRoot ".gating"
if (-not (Test-Path -LiteralPath $gateDir)) {
  $gateDir = $RepoRoot
}

# Contract (repo invariants)
& (Join-Path $gateDir "contract/ps1/root-contract-check.ps1") -RepoRoot $RepoRoot
& (Join-Path $gateDir "contract/ps1/gitignore-template-check.ps1") -RepoRoot $RepoRoot

# Linting (fast checks)
# JS checks require node
$node = Get-Command node -ErrorAction SilentlyContinue
if ($null -ne $node) {
  node (Join-Path $gateDir "linting/js/no-plural-check.js") --path $RepoRoot
  node (Join-Path $gateDir "linting/js/layer-mirror-check.js") --path $RepoRoot
  node (Join-Path $gateDir "linting/js/doc-name-check.js") --root $RepoRoot
  if (-not [string]::IsNullOrWhiteSpace($ArchiveZips)) {
    foreach ($zip in $ArchiveZips -split '\s+') {
      if ($zip -ne "") {
        node (Join-Path $gateDir "linting/js/archive-name-check.js") $zip
      }
    }
  } else {
    Write-Host "ARCHIVE_ZIPS not set, skipping archive-name-check"
  }
} else {
  Write-Host "node not found, skipping JS linting checks"
}

& (Join-Path $gateDir "linting/ps1/copyright-header-check.ps1") -RepoRoot $RepoRoot
& (Join-Path $gateDir "linting/ps1/layer-mirror-check.ps1") -RepoRoot $RepoRoot
& (Join-Path $gateDir "linting/ps1/doc-name-check.ps1") -RepoRoot $RepoRoot
if (-not [string]::IsNullOrWhiteSpace($ArchiveZips)) {
  foreach ($zip in $ArchiveZips -split '\s+') {
    if ($zip -ne "") {
      & (Join-Path $gateDir "linting/ps1/archive-flat-root-check.ps1") -ZipPath $zip
    }
  }
} else {
  Write-Host "ARCHIVE_ZIPS not set, skipping archive-flat-root-check"
}

if ($Quality) {
  & (Join-Path $gateDir "quality/ps1/quality-run.ps1") -RepoRoot $RepoRoot
}

Write-Host "Gate OK"
