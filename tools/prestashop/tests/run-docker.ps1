param(
    [ValidateSet("1.6.1.0", "1.6-5.6-apache", "1.7.6-apache", "1.7.8-apache", "8.2-apache", "9.0-apache", "9.2.1-8.1")]
    [string]$PrestaShopTag = "9.2.1-8.1",
    [int]$PrestaShopPort = 8088
)

$ErrorActionPreference = "Stop"
$env:PRESTASHOP_TAG = $PrestaShopTag
$env:PRESTASHOP_PORT = "$PrestaShopPort"
$isPrestaShop16 = $PrestaShopTag.StartsWith("1.6")
$isPrestaShop17 = $PrestaShopTag.StartsWith("1.7.")
$packageLine = if ($isPrestaShop16) { "16" } elseif ($isPrestaShop17) { "17" } elseif ($PrestaShopTag -eq "8.2-apache") { "8" } else { "9" }
$env:APPNUNO_PRESTASHOP_LINE = if ($isPrestaShop16) { "1.6.x" } elseif ($isPrestaShop17) { "1.7.x" } else { "$packageLine.x" }
$env:MYSQL_TAG = if ($PrestaShopTag -eq "1.6.1.0") { "5.6" } elseif ($isPrestaShop16 -or $isPrestaShop17) { "5.7" } else { "8.0" }
php (Join-Path $PSScriptRoot "..\build-package.php") $packageLine
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
$composeFile = Join-Path $PSScriptRoot "docker\docker-compose.yml"
$composeArgs = @("-f", $composeFile)
if ($isPrestaShop16) {
    $composeArgs += @("-f", (Join-Path $PSScriptRoot "docker\docker-compose.mysql-legacy.yml"))
}
$exitCode = 0

try {
    docker compose @composeArgs up --build --abort-on-container-exit --exit-code-from installer-check installer-check
    $exitCode = $LASTEXITCODE
} finally {
    docker compose @composeArgs down --volumes --remove-orphans
    if ($LASTEXITCODE -ne 0 -and $exitCode -eq 0) {
        $exitCode = $LASTEXITCODE
    }
}

exit $exitCode
