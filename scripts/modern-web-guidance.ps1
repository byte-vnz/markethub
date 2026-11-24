# Development-only runner: keep guidance telemetry and optional query logs disabled.
$mwgSettings = @{
    DISABLE_TELEMETRY = '1'
    DO_NOT_TRACK = '1'
    ENABLE_FILE_LOGGING = 'false'
    NPM_CONFIG_IGNORE_SCRIPTS = 'true'
    NPM_CONFIG_CACHE = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../.qa/modern-web-guidance/npm-cache'))
}
$mwgPrevious = @{}
try {
    foreach ($name in $mwgSettings.Keys) {
        $mwgPrevious[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
        [Environment]::SetEnvironmentVariable($name, $mwgSettings[$name], 'Process')
    }
    Get-Command npx.cmd -ErrorAction Stop | Out-Null
    & npx.cmd --yes modern-web-guidance@1.0.1 @args
    $mwgExitCode = $LASTEXITCODE
} finally {
    foreach ($name in $mwgPrevious.Keys) {
        [Environment]::SetEnvironmentVariable($name, $mwgPrevious[$name], 'Process')
    }
}
exit $mwgExitCode
