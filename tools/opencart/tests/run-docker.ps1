param(
    [string]$OpenCartVersion = "2.3.0.2"
)

$ErrorActionPreference = "Stop"
if ($OpenCartVersion -notin @("2.3.0.0", "2.3.0.1", "2.3.0.2")) {
    throw "The Docker test runner currently supports OpenCart 2.3.0.0, 2.3.0.1, and 2.3.0.2."
}
$env:OPENCART_VERSION = $OpenCartVersion
$composeFile = Join-Path $PSScriptRoot "docker\docker-compose.yml"
$exitCode = 0

try {
    docker compose -f $composeFile up --build --abort-on-container-exit --exit-code-from runner runner
    $exitCode = $LASTEXITCODE
} finally {
    docker compose -f $composeFile down --volumes --remove-orphans
    if ($LASTEXITCODE -ne 0 -and $exitCode -eq 0) {
        $exitCode = $LASTEXITCODE
    }
}

exit $exitCode
