# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
param(
  [Parameter(Mandatory=$false)]
  [Alias('Path')]
  [string]$RepoRoot = "."
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$resolved = (Resolve-Path -Path $RepoRoot).Path

# If caller passed the .gating folder as root (common when invoked from menu), normalize to parent.
if ((Split-Path -Leaf $resolved) -eq ".gating") {
  $resolved = Split-Path -Parent $resolved
}

$RepoRoot = $resolved

$Mode = if ($env:MODE) { $env:MODE } else { "owner" } # owner | industrial | both
$Quality = if ($env:QUALITY) { [bool]([int]$env:QUALITY) } else { $false }

$repoMode = "consumer"
if ($env:GITHUB_REPOSITORY -match "/canonization$") {
  $repoMode = "canon"
}

Write-Host "[gate] repo=$($env:GITHUB_REPOSITORY) repo_type=$repoMode mode=$Mode root=$RepoRoot"

function Should-RunScope([string]$scope) {
  switch ($Mode) {
    "owner" { return $scope -eq "owner" }
    "industrial" { return $scope -eq "industrial" }
    "both" { return ($scope -eq "owner" -or $scope -eq "industrial") }
    default { return $scope -eq "owner" }
  }
}

function Invoke-ChecksIndex([string]$scope) {
  $indexPath = Join-Path $RepoRoot ".gating/policy/$scope/checks.index.json"
  if (!(Test-Path -LiteralPath $indexPath)) {
    Write-Host "[gate] checks index missing: $indexPath"
    return
  }

  $index = Get-Content -LiteralPath $indexPath -Raw | ConvertFrom-Json
  foreach ($step in $index.steps) {
    if (-not (Should-RunScope $step.scope)) { continue }
    if ($step.repo_type -and $step.repo_type -ne $repoMode) {
      if ($step.id -eq "root-contract-check") {
        Write-Host "[gate] skip root-contract-check (consumer repo)"
      }
      continue
    }
    if ($step.when -and $step.when.quality -and -not $Quality) { continue }
    if (-not $step.ps1) { continue }

    $path = Join-Path $RepoRoot $step.ps1.path
    $args = @()
    foreach ($arg in ($step.ps1.args | ForEach-Object { $_ })) {
      if ($arg -eq '$RepoRoot') {
        $args += $RepoRoot
      } else {
        $args += $arg
      }
    }

    try {
      & $path @args
    } catch {
      Write-Host "[gate] FAIL step=$($step.id)"
      throw
    }
  }
}

switch ($Mode) {
  "industrial" { Invoke-ChecksIndex "industrial" }
  "both" {
    Invoke-ChecksIndex "owner"
    Invoke-ChecksIndex "industrial"
  }
  default { Invoke-ChecksIndex "owner" }
}

Write-Host "Gate OK"
