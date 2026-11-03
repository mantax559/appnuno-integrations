param(
    [ValidateSet("7.1", "7.1.1", "7.1.2", "7.1.3", "7.1.4")]
    [string]$WordPressVersion = "7.1.4",
    [int]$WordPressPort = 8080
)

$ErrorActionPreference = "Stop"
$env:WORDPRESS_VERSION = $WordPressVersion
$env:WORDPRESS_PORT = "$WordPressPort"
$env:WOOCOMMERCE_VERSION = "11.2.1"
$composeFile = Join-Path $PSScriptRoot "docker-compose.yml"
$exitCode = 0

try {
    docker compose -f $composeFile up --build --abort-on-container-exit --exit-code-from installer installer
    $exitCode = $LASTEXITCODE
} finally {
    docker compose -f $composeFile down --volumes --remove-orphans
    if ($LASTEXITCODE -ne 0 -and $exitCode -eq 0) {
        $exitCode = $LASTEXITCODE
    }
}

exit $exitCode
