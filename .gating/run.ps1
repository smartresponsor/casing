# Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$gating = Join-Path $PSScriptRoot 'bin\gating'
php $gating check --target $PSScriptRoot
exit $LASTEXITCODE
