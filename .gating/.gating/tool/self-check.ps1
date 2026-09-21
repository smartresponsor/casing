$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$GatingDir = Resolve-Path (Join-Path $ScriptDir '..')
php (Join-Path $GatingDir 'bin/gating') self-check @args
