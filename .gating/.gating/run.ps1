# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$DotDir = Split-Path -Parent $MyInvocation.MyCommand.Path

function Normalize-RepoRoot([string]$Path) {
  $p = (Resolve-Path -Path $Path).Path
  if ((Split-Path -Leaf $p) -eq ".gating") {
    return (Split-Path -Parent $p)
  }
  return $p
}

function Resolve-RepoRoot {
  param([string]$StartDir)

  $d = (Resolve-Path -Path $StartDir).Path
  for ($i = 0; $i -lt 50; $i++) {
    $gateDir = Join-Path $d ".gating"
    if ((Test-Path -LiteralPath (Join-Path $gateDir "gate.sh") -PathType Leaf)) {
      return $d
    }
    $p = Split-Path -Parent $d
    if ($p -eq $d -or [string]::IsNullOrWhiteSpace($p)) { break }
    $d = $p
  }
  return (Resolve-Path -Path (Join-Path $StartDir "..")).Path
}

$Root = if ($env:REPO_ROOT) { Normalize-RepoRoot $env:REPO_ROOT } else { Resolve-RepoRoot -StartDir $DotDir }
$Root = Normalize-RepoRoot $Root

$GatePs1 = Join-Path $Root ".gating\gate.ps1"
$GateSh  = Join-Path $Root ".gating\gate.sh"

if (Test-Path -LiteralPath $GatePs1 -PathType Leaf) {
  pwsh -NoProfile -File $GatePs1 -Path $Root
  exit $LASTEXITCODE
}

if (-not (Get-Command bash -ErrorAction SilentlyContinue)) {
  throw "bash not found. Install Git for Windows (Git Bash) or provide bash in PATH."
}

if (-not (Test-Path -LiteralPath $GateSh -PathType Leaf)) {
  throw "[run] missing gate.sh ($GateSh)"
}

Push-Location $Root
try {
  bash $GateSh $Root
  exit $LASTEXITCODE
} finally {
  Pop-Location
}
