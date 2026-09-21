param(
  [Parameter(Mandatory=$false)][string]$RepoRoot = (Get-Location).Path
)

$ErrorActionPreference = "Stop"

$contractPath = Join-Path $RepoRoot ".gating/contract/contract.json"
if (-not (Test-Path $contractPath)) { throw "contract.json not found: $contractPath" }

$contract = Get-Content $contractPath -Raw | ConvertFrom-Json

$requiredFiles = @($contract.root_contract.required_root_files)
$allowedFilesExact = @($contract.root_contract.allowed_root_files_exact)
$allowedDots = @($contract.allowed_root_dot_folders)

$items = Get-ChildItem -LiteralPath $RepoRoot -Force
$bad = @()

foreach ($it in $items) {
  $name = $it.Name
  if ($it.PSIsContainer) {
    if (-not $name.StartsWith(".")) {
      $bad += "Non-dot folder in root: $name"
      continue
    }
    if ($allowedDots.Count -gt 0 -and ($allowedDots -notcontains $name)) {
      $bad += "Unexpected dot-folder in root: $name"
    }
  } else {
    if ($allowedFilesExact -notcontains $name) {
      $bad += "Unexpected root file: $name"
    }
  }
}

foreach ($rf in $requiredFiles) {
  if (-not (Test-Path (Join-Path $RepoRoot $rf))) {
    $bad += "Missing required root file: $rf"
  }
}

if ($bad.Count -gt 0) {
  Write-Host ""
  Write-Host "Root contract FAILED:" -ForegroundColor Red
  $bad | ForEach-Object { Write-Host (" - " + $_) -ForegroundColor Red }
  Write-Host ""
  exit 2
}

Write-Host "Root contract OK"
