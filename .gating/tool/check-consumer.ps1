param(
    [Parameter(Mandatory = $true)][string]$Target,
    [Parameter(Mandatory = $true)][string]$Profile
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$targetPath = [System.IO.Path]::GetFullPath($Target)
$profilePath = if ([System.IO.Path]::IsPathRooted($Profile)) {
    [System.IO.Path]::GetFullPath($Profile)
} else {
    [System.IO.Path]::GetFullPath((Join-Path $root $Profile))
}

if (-not (Test-Path -LiteralPath $targetPath -PathType Container)) {
    throw "Consumer target not found: $targetPath"
}
if (-not (Test-Path -LiteralPath $profilePath -PathType Leaf)) {
    throw "Gating profile not found: $profilePath"
}

$policyRoot = Join-Path $root '.gating'
& php (Join-Path $root 'bin/gating') check "--target=$targetPath" "--profile=$profilePath" "--policy-root=$policyRoot"
exit $LASTEXITCODE
