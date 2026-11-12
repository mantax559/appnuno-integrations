param(
    [ValidateSet("8.2-apache", "9.0-apache")]
    [string]$PrestaShopTag = "8.2-apache",
    [int]$PrestaShopPort = 8088
)

$ErrorActionPreference = "Stop"
$env:PRESTASHOP_TAG = $PrestaShopTag
$env:PRESTASHOP_PORT = "$PrestaShopPort"
if ($PrestaShopTag -eq "9.0-apache") {
    php (Join-Path $PSScriptRoot "..\build-package.php")
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}
$composeFile = Join-Path $PSScriptRoot "docker\docker-compose.yml"
$liveComposeFile = Join-Path $PSScriptRoot "docker\docker-compose.live.yml"
$composeArgs = @("-p", "appnuno-prestashop-live", "-f", $composeFile, "-f", $liveComposeFile)

docker compose @composeArgs up -d --wait mysql prestashop
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

docker compose @composeArgs run --rm installer-check
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

if ($PrestaShopTag -eq "9.0-apache") {
    docker compose @composeArgs exec -T prestashop php /scripts/prepare-module.php
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

Write-Host "PrestaShop is available at http://127.0.0.1:$PrestaShopPort"
if ($PrestaShopTag -eq "9.0-apache") {
    Write-Host "Admin: admin@example.test / appnuno-local"
    Write-Host "Customer: customer@example.test / appnuno-local"
}
Write-Host "PrestaShop is installed; the live containers and data remain running."
