param(
  [Parameter(Mandatory=$false)][string]$RepoRoot = (Get-Location).Path
)

$ErrorActionPreference = "Stop"

$contractPath = Join-Path $RepoRoot ".gating/contract/contract.json"
$gitignorePath = Join-Path $RepoRoot ".gitignore"

if (-not (Test-Path $contractPath)) { throw "contract.json not found: $contractPath" }
if (-not (Test-Path $gitignorePath)) { throw ".gitignore not found: $gitignorePath" }

$contract = Get-Content $contractPath -Raw | ConvertFrom-Json
$must = @($contract.gitignore_template.must_include_all)

$content = Get-Content $gitignorePath -Raw

$missing = @()
foreach ($m in $must) {
  if ($content -notmatch [regex]::Escape($m)) {
    $missing += $m
  }
}

if ($missing.Count -gt 0) {
  Write-Host ""
  Write-Host ".gitignore template FAILED:" -ForegroundColor Red
  $missing | ForEach-Object { Write-Host (" - missing: " + $_) -ForegroundColor Red }
  Write-Host ""
  exit 3
}

Write-Host ".gitignore template OK"
