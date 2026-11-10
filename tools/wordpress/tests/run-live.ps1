param(
    [ValidateSet("7.1", "7.1.1", "7.1.2", "7.1.3", "7.1.4")]
    [string]$WordPressVersion = "7.1.4",
    [int]$WordPressPort = 8080,
    [string]$WooCommerceVersion = "11.2.1"
)

$ErrorActionPreference = "Stop"
$env:WORDPRESS_VERSION = $WordPressVersion
$env:WORDPRESS_PORT = "$WordPressPort"
$env:WOOCOMMERCE_VERSION = $WooCommerceVersion
$env:APPNUNO_INSTALL_PLUGIN = "1"
$composeFile = Join-Path $PSScriptRoot "docker\docker-compose.yml"
$liveComposeFile = Join-Path $PSScriptRoot "docker\docker-compose.live.yml"
$composeArgs = @("-p", "appnuno-wordpress-live", "-f", $composeFile, "-f", $liveComposeFile)

docker compose @composeArgs up -d --build mysql wordpress
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

docker compose @composeArgs run --rm live-init
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

Write-Host "WordPress is available at http://127.0.0.1:$WordPressPort"
Write-Host "WordPress and WooCommerce are installed; the live containers and data remain running."
