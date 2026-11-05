param(
    [ValidateSet("8.2-apache", "9.0-apache")]
    [string]$PrestaShopTag = "8.2-apache",
    [int]$PrestaShopPort = 8088
)

$ErrorActionPreference = "Stop"
$env:PRESTASHOP_TAG = $PrestaShopTag
$env:PRESTASHOP_PORT = "$PrestaShopPort"
$composeFile = Join-Path $PSScriptRoot "docker\docker-compose.yml"
$exitCode = 0

try {
    docker compose -f $composeFile up --build --abort-on-container-exit --exit-code-from installer-check installer-check
    $exitCode = $LASTEXITCODE
} finally {
    docker compose -f $composeFile down --volumes --remove-orphans
    if ($LASTEXITCODE -ne 0 -and $exitCode -eq 0) {
        $exitCode = $LASTEXITCODE
    }
}

exit $exitCode
