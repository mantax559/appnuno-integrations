param(
    [ValidateSet("2.3.0.0", "2.3.0.1", "2.3.0.2", "4.1.0.4")]
    [string]$OpenCartVersion = "4.1.0.4",
    [int]$OpenCartPort = 8080
)

$ErrorActionPreference = "Stop"
$env:OPENCART_VERSION = $OpenCartVersion
$env:OPENCART_PHP_VERSION = if ($OpenCartVersion -eq "4.1.0.4") { "8.1" } else { "7.3" }
$env:OPENCART_PHP_BASE_IMAGE = if ($OpenCartVersion -eq "4.1.0.4") { "php:8.1-cli-bookworm" } else { "php:7.3-cli" }
$env:OPENCART_PORT = "$OpenCartPort"
$composeFile = Join-Path $PSScriptRoot "docker\docker-compose.yml"
$liveComposeFile = Join-Path $PSScriptRoot "docker\docker-compose.live.yml"
$composeArgs = @("-p", "appnuno-opencart-live", "-f", $composeFile, "-f", $liveComposeFile)

docker compose @composeArgs up -d --build mysql opencart-php
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

docker compose @composeArgs run --build --rm live-init
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

docker compose @composeArgs up -d --wait opencart-php
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

Write-Host "OpenCart is available at http://127.0.0.1:$OpenCartPort"
Write-Host "The OpenCart installer is ready; the live containers and data remain running."
