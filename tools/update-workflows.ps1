param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot "..\.github\workflows")
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

& (Join-Path $PSScriptRoot "update-woocommerce-workflows.ps1") -OutputDirectory $OutputDirectory
& (Join-Path $PSScriptRoot "update-opencart-workflows.ps1") -OutputDirectory $OutputDirectory

Write-Output "Generated WooCommerce and OpenCart workflows."
