$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$files = @(
    'gate.ps1',
    'gate.sh',
    'contract/ps1/root-contract-check.ps1',
    'contract/ps1/gitignore-template-check.ps1',
    'contract/sh/root-contract-check.sh',
    'contract/sh/gitignore-template-check.sh',
    'linting/ps1/doc-name-check.ps1',
    'linting/ps1/layer-mirror-check.ps1',
    'linting/sh/doc-name-check.sh',
    'linting/sh/layer-mirror-check.sh',
    'quality/ps1/phpstan-run.ps1',
    'quality/ps1/quality-run.ps1',
    'quality/ps1/rector-run.ps1',
    'quality/sh/quality-run.sh',
    'contract/contract.json',
    'quality/MANIFEST.json',
    'contract/README.md'
)

foreach ($relative in $files) {
    $path = Join-Path $root $relative
    $text = [System.IO.File]::ReadAllText($path)
    $updated = $text.Replace('.gate/', '.gating/')
    $updated = $updated.Replace('".gate"', '".gating"')
    $updated = $updated.Replace("'.gate'", "'.gating'")
    $updated = $updated.Replace('$REPO_ROOT/.gate', '$REPO_ROOT/.gating')
    $updated = $updated.Replace('$ROOT/.gate', '$ROOT/.gating')
    if ($updated -cne $text) {
        [System.IO.File]::WriteAllText($path, $updated, [System.Text.UTF8Encoding]::new($false))
    }
}

Write-Host 'Canonical .gating compatibility paths applied.'
