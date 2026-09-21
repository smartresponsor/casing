param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $GatingArgument
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$GatingRoot = Split-Path -Parent $ScriptDir
$EntryPoint = Join-Path $GatingRoot 'bin/gating'
& php $EntryPoint @GatingArgument
exit $LASTEXITCODE
