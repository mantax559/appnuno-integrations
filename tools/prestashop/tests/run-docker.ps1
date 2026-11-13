param(
    [ValidateSet("1.7.6-apache", "1.7.8-apache", "8.2-apache", "9.0-apache")]
    [string]$PrestaShopTag = "8.2-apache",
    [int]$PrestaShopPort = 8088
)

$ErrorActionPreference = "Stop"
$env:PRESTASHOP_TAG = $PrestaShopTag
$env:PRESTASHOP_PORT = "$PrestaShopPort"
$isPrestaShop17 = $PrestaShopTag.StartsWith("1.7.")
$packageLine = if ($isPrestaShop17) { "17" } elseif ($PrestaShopTag -eq "8.2-apache") { "8" } else { "9" }
$env:APPNUNO_PRESTASHOP_LINE = if ($isPrestaShop17) { "1.7.x" } else { "$packageLine.x" }
$env:MYSQL_TAG = if ($isPrestaShop17) { "5.7" } else { "8.0" }
php (Join-Path $PSScriptRoot "..\build-package.php") $packageLine
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
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
